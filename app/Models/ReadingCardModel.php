<?php

namespace App\Models;

use CodeIgniter\Model;

class ReadingCardModel extends Model
{
    protected $table = 'reading_cards';
    protected $primaryKey = 'id';
    protected $allowedFields = ['reading_id', 'card_id', 'position', 'orientation', 'notes'];

    /**
     * Fetch all cards for a specific reading
     */
    public function getCardsByReading(int $readingId)
    {
        return $this->where('reading_id', $readingId)
            ->join('tarot_cards', 'tarot_cards.id = reading_cards.card_id')
            ->findAll();
    }
}
