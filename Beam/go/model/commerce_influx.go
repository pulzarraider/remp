package model

import (
	"fmt"
	"log"

	"github.com/influxdata/influxdb/client/v2"
	"github.com/pkg/errors"
	"gitlab.com/remp/remp/Beam/go/influxquery"
)

// CommerceInflux is Influx implementation of CommerceStorage.
type CommerceInflux struct {
	DB *InfluxDB
}

// Count returns count of events based on the provided filter options.
func (cDB *CommerceInflux) Count(o AggregateOptions) (CountRowCollection, bool, error) {
	builder := cDB.DB.QueryBuilder.Select(`count("revenue")`).From(`"` + TableCommerce + `"`)
	builder = addAggregateQueryFilters(builder, o)

	bb := builder.Build()
	log.Println("commerce count query:", bb)

	q := client.Query{
		Command:  bb,
		Database: cDB.DB.DBName,
	}

	response, err := cDB.DB.Client.Query(q)
	if err != nil {
		return nil, false, err
	}
	if response.Error() != nil {
		return nil, false, response.Error()
	}

	// process response
	return cDB.DB.MultiGroupedCount(response)
}

// List returns list of all events based on given CommerceOptions.
func (cDB *CommerceInflux) List(o ListOptions) (CommerceRowCollection, error) {
	// not implemented; the original implementation was non-functional
	return CommerceRowCollection{}, nil
}

// Sum returns sum of events based on the provided filter options.
func (cDB *CommerceInflux) Sum(o AggregateOptions) (SumRowCollection, bool, error) {
	builder := cDB.DB.QueryBuilder.Select(`sum("revenue")`).From(`"` + TableCommerce + `"`)
	builder = addAggregateQueryFilters(builder, o)

	bb := builder.Build()
	log.Println("commerce sum query:", bb)

	q := client.Query{
		Command:  bb,
		Database: cDB.DB.DBName,
	}

	response, err := cDB.DB.Client.Query(q)
	if err != nil {
		return nil, false, err
	}
	if response.Error() != nil {
		return nil, false, response.Error()
	}

	// process response
	return cDB.DB.GroupedSum(response)
}

// Categories lists all available categories.
func (cDB *CommerceInflux) Categories() []string {
	return []string{
		CategoryCommerce,
	}
}

// Flags lists all available flags.
func (cDB *CommerceInflux) Flags() []string {
	return []string{}
}

// Actions lists all available actions under the given category.
func (cDB *CommerceInflux) Actions(category string) ([]string, error) {
	switch category {
	case CategoryCommerce:
		return []string{
			"checkout",
			"payment",
			"purchase",
			"refund",
		}, nil
	}
	return nil, fmt.Errorf("unknown commerce category: %s", category)
}

func commerceFromInfluxResult(ir *influxquery.Result) (*Commerce, error) {
	token, ok := ir.StringValue("token")
	if !ok {
		return nil, errors.New("unable to map Token to influx result column")
	}
	t, ok, err := ir.TimeValue("time")
	if err != nil {
		return nil, err
	}
	if !ok {
		return nil, errors.New("unable to map Time to influx result column")
	}
	commerce := &Commerce{
		Token: token,
		Time:  t,
	}

	host, ok := ir.StringValue("host")
	if ok {
		commerce.Host = host
	}
	ip, ok := ir.StringValue("ip")
	if ok {
		commerce.IP = ip
	}
	userID, ok := ir.StringValue("user_id")
	if ok {
		commerce.UserID = userID
	}
	url, ok := ir.StringValue("url")
	if ok {
		commerce.URL = url
	}
	userAgent, ok := ir.StringValue("user_agent")
	if ok {
		commerce.UserAgent = userAgent
	}

	return commerce, nil
}
