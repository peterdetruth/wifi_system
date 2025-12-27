<?php

namespace App\Models;

use CodeIgniter\Model;

class ReadingModel extends Model
{
    protected $table = 'readings';
    protected $primaryKey = 'id';
    protected $allowedFields = ['user_id', 'reading_date', 'reading_type', 'notes', 'created_at', 'updated_at'];
    protected $useTimestamps = true;

    /**
     * Fetch a reading with its cards and card details
     */
    public function getWithCards(int $readingId)
    {
        return $this->select('readings.*, reading_cards.position, reading_cards.orientation, reading_cards.notes as card_note, tarot_cards.name as card_name, tarot_cards.upright_meaning, tarot_cards.reversed_meaning')
            ->join('reading_cards', 'reading_cards.reading_id = readings.id')
            ->join('tarot_cards', 'tarot_cards.id = reading_cards.card_id')
            ->where('readings.id', $readingId)
            ->findAll();
    }

    /**
     * Optional: fetch readings filtered by user, type, or date
     */
    public function getFiltered($filters = [])
    {
        $builder = $this;
        if (isset($filters['user_id'])) $builder->where('user_id', $filters['user_id']);
        if (isset($filters['reading_type'])) $builder->where('reading_type', $filters['reading_type']);
        if (isset($filters['start_date'])) $builder->where('reading_date >=', $filters['start_date']);
        if (isset($filters['end_date'])) $builder->where('reading_date <=', $filters['end_date']);
        return $builder->findAll();
    }
}
