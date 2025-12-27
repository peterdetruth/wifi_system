<?php

namespace App\Models;

use CodeIgniter\Model;

class TarotCardModel extends Model
{
    protected $table = 'tarot_cards';
    protected $primaryKey = 'id';
    protected $allowedFields = ['name', 'arcana_type', 'suit', 'upright_meaning', 'reversed_meaning', 'created_at', 'updated_at'];
    protected $useTimestamps = true;

    /**
     * Get random cards from deck
     */
    public function getRandom(int $count)
    {
        return $this->orderBy('RAND()')->findAll($count);
    }
}
