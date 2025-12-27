<?php

namespace App\Controllers;

use App\Models\ReadingModel;
use App\Models\ReadingCardModel;
use App\Models\TarotCardModel;
use App\Models\StatsModel;

class ReadingController extends BaseController
{
    protected $readingModel;
    protected $readingCardModel;
    protected $tarotCardModel;
    protected $statsModel;

    public function __construct()
    {
        $this->readingModel = new ReadingModel();
        $this->readingCardModel = new ReadingCardModel();
        $this->tarotCardModel = new TarotCardModel();
        $this->statsModel = new StatsModel();
    }

    // List all readings
    public function index()
    {
        $readings = $this->readingModel->findAll();
        // return view('readings/index', ['readings' => $readings]);
    }

    // Show form to create reading
    public function create()
    {
        // Load all tarot cards
        $cards = $this->tarotCardModel->findAll();

        // Load all users/clients
        $userModel = new \App\Models\UserModel();
        $users = $userModel->findAll();

        // Pass both cards and users to the view
        return view('readings/create', [
            'cards' => $cards,
            'users' => $users
        ]);
    }


    // Store new reading
    public function store()
    {
        // 1. Insert reading
        $readingId = $this->readingModel->insert([
            'user_id' => $this->request->getPost('user_id'),
            'reading_date' => $this->request->getPost('reading_date'),
            'reading_type' => $this->request->getPost('reading_type'),
            'notes' => $this->request->getPost('notes')
        ]);

        // 2. Insert each card
        $cards = $this->request->getPost('cards'); // array of card_id, position, orientation
        foreach ($cards as $c) {
            $this->readingCardModel->insert([
                'reading_id' => $readingId,
                'card_id' => $c['card_id'],
                'position' => $c['position'],
                'orientation' => $c['orientation'],
                'notes' => $c['notes'] ?? null
            ]);

            // 3. Update stats
            $this->statsModel->incrementCard($c['card_id'], $c['orientation'], $c['position']);
        }

        // redirect to show page
        return redirect()->to("/readings/{$readingId}");
    }

    // Show reading details
    public function show($id)
    {
        $reading = $this->readingModel->getWithCards($id);
        // return view('readings/show', ['reading' => $reading]);
    }

    // Edit reading
    public function edit($id)
    {
        $reading = $this->readingModel->find($id);
        $cards = $this->readingCardModel->getCardsByReading($id);
        // return view('readings/edit', ['reading' => $reading, 'cards' => $cards]);
    }

    // Update reading
    public function update($id)
    {
        $this->readingModel->update($id, [
            'notes' => $this->request->getPost('notes')
            // optionally update other fields
        ]);

        // optionally update reading_cards and stats
    }

    // Delete reading
    public function destroy($id)
    {
        $this->readingModel->delete($id);
        // related reading_cards are deleted via cascade
        // optionally update stats
        // redirect back
    }

    // Show stats
    public function stats()
    {
        // fetch stats via StatsModel or dynamic queries
    }
}
