<?php

namespace App\Models;

use CodeIgniter\Model;

class StatsModel extends Model
{
    protected $table = 'reading_stats';
    protected $primaryKey = 'card_id';
    protected $allowedFields = ['card_id', 'total_draws', 'upright_count', 'reversed_count', 'most_common_position', 'last_drawn_at'];

    /**
     * Increment stats for a card
     */
    public function incrementCard(int $cardId, string $orientation, string $position)
    {
        $stat = $this->find($cardId);
        if ($stat) {
            $stat['total_draws']++;
            $stat['upright_count'] += ($orientation === 'upright') ? 1 : 0;
            $stat['reversed_count'] += ($orientation === 'reversed') ? 1 : 0;
            $stat['most_common_position'] = $position; // simple update, can improve with logic
            $stat['last_drawn_at'] = date('Y-m-d H:i:s');
            $this->update($cardId, $stat);
        } else {
            $this->insert([
                'card_id' => $cardId,
                'total_draws' => 1,
                'upright_count' => ($orientation === 'upright') ? 1 : 0,
                'reversed_count' => ($orientation === 'reversed') ? 1 : 0,
                'most_common_position' => $position,
                'last_drawn_at' => date('Y-m-d H:i:s')
            ]);
        }
    }
}
