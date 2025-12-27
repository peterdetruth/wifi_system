<?php

namespace App\Controllers;

use App\Models\TarotCardModel;

class TarotCardController extends BaseController
{
    protected $tarotCardModel;

    public function __construct()
    {
        $this->tarotCardModel = new TarotCardModel();
    }

    // List all cards
    public function index()
    {
        $cards = $this->tarotCardModel->findAll();
        return view('cards/index', ['cards' => $cards]);
    }

    // Show form to add a new card
    public function create()
    {
        return view('cards/create');
    }

    // Save new card
    public function store()
    {
        $this->tarotCardModel->insert([
            'name' => $this->request->getPost('name'),
            'arcana_type' => $this->request->getPost('arcana_type'),
            'suit' => $this->request->getPost('suit'),
            'upright_meaning' => $this->request->getPost('upright_meaning'),
            'reversed_meaning' => $this->request->getPost('reversed_meaning')
        ]);

        return redirect()->to('/cards')->with('success', 'Card added successfully.');
    }
}
