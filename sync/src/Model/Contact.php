<?php

namespace App\Model;

use App\Model;
use Belt\Belt;
use App\Traits\Model as ModelTrait;
use App\Exceptions\DatabaseUpdate as DatabaseUpdateException;

class Contact extends Model
{
    public $id;
    public $name;
    public $tally;
    public $account_id;
    public $created_at;

    use ModelTrait;

    public function getData()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'tally' => $this->tally,
            'account_id' => $this->account_id,
            'created_at' => $this->created_at
        ];
    }

    /**
     * Adds a collection of contacts at once. If any already
     * exist in the database, this will update the tally.
     *
     * @throws DatabaseUpdateException
     */
    public function store(array $keys, array $data)
    {
        $updated = $this->db()
            ->insertMulti($keys, $data)
            ->into('contacts')
            ->onDuplicateKeyUpdate(['tally'])
            ->execute();

        if (! Belt::isNumber($updated)) {
            throw new DatabaseUpdateException(
                META,
                $this->db()->getError());
        }
    }
}
