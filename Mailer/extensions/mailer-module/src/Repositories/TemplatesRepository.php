<?php
declare(strict_types=1);

namespace Remp\MailerModule\Repositories;

use Nette\Utils\DateTime;
use Remp\MailerModule\Models\ContentGenerator\GeneratorInput;

class TemplatesRepository extends Repository
{
    protected $tableName = 'mail_templates';

    protected $dataTableSearchable = ['name', 'code', 'description', 'subject'];
    protected $dataTableSearchableFullText = ['mail_body_html'];

    public function all(): Selection
    {
        return $this->getTable()->order('created_at DESC');
    }

    public function pairs(int $listId): array
    {
        return $this->all()->select('id, name')->where(['mail_type_id' => $listId])->fetchPairs('id', 'name');
    }

    public function triples(): array
    {
        $result = [];
        foreach ($this->all()->select('id, name, mail_type_id') as $template) {
            $result[$template->mail_type_id][] = [
                'value' => $template->id,
                'label' => $template->name,
            ];
        }
        return $result;
    }

    public function add(
        string $name,
        string $code,
        string $description,
        string $from,
        string $subject,
        string $templateText,
        string $templateHtml,
        int $layoutId,
        int $typeId,
        ?bool $clickTracking = null,
        ?string $extrasJson = null,
        ?string $paramsJson = null,
        bool $attachmentsEnabled = true
    ): ActiveRow {
        if ($this->exists($code)) {
            throw new TemplatesCodeNotUniqueException("Template code [$code] is already used.");
        }

        $result = $this->insert([
            'name' => $name,
            'code' => $code,
            'description' => $description,
            'from' => $from,
            'autologin' => true,
            'subject' => $subject,
            'click_tracking' => $clickTracking,
            'mail_body_text' => $templateText,
            'mail_body_html' => $templateHtml,
            'mail_layout_id' => $layoutId,
            'mail_type_id' => $typeId,
            'created_at' => new DateTime(),
            'updated_at' => new DateTime(),
            'extras' => $extrasJson,
            'params' => $paramsJson,
            'attachments_enabled' => $attachmentsEnabled
        ]);

        if (is_numeric($result)) {
            return $this->getTable()->where('id', $result)->fetch();
        }

        return $result;
    }

    public function update(ActiveRow &$row, array $data): bool
    {
        // if code changed, check if it's unique
        if (isset($data['code']) && $row['code'] != $data['code'] && $this->exists($data['code'])) {
            throw new TemplatesCodeNotUniqueException("Template code [" . $data['code'] . "] is already used.");
        }
        $data['updated_at'] = new DateTime();
        return parent::update($row, $data);
    }

    public function duplicate(ActiveRow $template)
    {
        return $this->insert([
            'name' => $template->name . ' (copy)',
            'code' => $this->getUniqueTemplateCode($template->code),
            'description' => $template->description,
            'from' => $template->from,
            'subject' => $template->subject,
            'mail_body_text' => $template->mail_body_text,
            'mail_body_html' => $template->mail_body_html,
            'mail_layout_id' => $template->mail_layout_id,
            'mail_type_id' => $template->mail_type_id,
            'copy_from' => $template->id,
            'created_at' => new DateTime(),
            'updated_at' => new DateTime(),
            'extras' => $template->extras,
            'params' => $template->params,
            'attachments_enabled' => $template->attachments_enabled,
        ]);
    }

    public function exists(string $code): bool
    {
        return $this->getTable()->where('code', $code)->count('*') > 0;
    }

    public function getByCode($code)
    {
        return $this->getTable()->where('code', $code)->fetch();
    }

    public function getUniqueTemplateCode($codeBase)
    {
        $index = 0;
        do {
            $code = $codeBase . '-' . $index;
            if ($index == 0) {
                $code = $codeBase;
            }
            $index++;
        } while ($this->exists($code));

        return $code;
    }

    public function tableFilter(string $query, string $order, string $orderDirection, ?array $listId = null, ?int $limit = null, ?int $offset = null): Selection
    {
        $selection = $this->getTable()
            ->order($order . ' ' . strtoupper($orderDirection));

        if (!empty($query)) {
            $where = [];
            foreach ($this->dataTableSearchable as $col) {
                $where[$col . ' LIKE ?'] = '%' . $query . '%';
            }

            foreach ($this->dataTableSearchableFullText as $col) {
                $where['MATCH('.$col . ') AGAINST(? IN BOOLEAN MODE)'] = '+' . $query . '*';
            }
            $selection->whereOr($where);
        }

        if ($listId !== null) {
            $selection->where([
                'mail_type_id' => $listId
            ]);
        }

        if ($limit !== null) {
            $selection->limit($limit, $offset);
        }

        return $selection;
    }

    public function search(string $term, int $limit)
    {
        $searchable = ['code', 'name', 'subject', 'description'];
        foreach ($searchable as $column) {
            $whereFast[$column . ' LIKE ?'] = $term . '%';
            $whereWild[$column . ' LIKE ?'] = '%' . $term . '%';
        }

        $resultsFast = $this->all()
            ->whereOr($whereFast)
            ->limit($limit)
            ->fetchAll();
        if (count($resultsFast) === $limit) {
            return $resultsFast;
        }

        $resultsWild = $this->all()
            ->whereOr($whereWild)
            ->limit($limit - count($resultsFast))
            ->fetchAll();

        return array_merge($resultsFast, $resultsWild);
    }
}