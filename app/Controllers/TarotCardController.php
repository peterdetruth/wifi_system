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

    // Show form to edit a card
    public function edit($id)
    {
        $card = $this->tarotCardModel->find($id);
        if (!$card) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound("Card not found.");
        }
        return view('cards/edit', ['card' => $card]);
    }

    // Update card
    public function update($id)
    {
        $this->tarotCardModel->update($id, [
            'name' => $this->request->getPost('name'),
            'arcana_type' => $this->request->getPost('arcana_type'),
            'suit' => $this->request->getPost('suit'),
            'upright_meaning' => $this->request->getPost('upright_meaning'),
            'reversed_meaning' => $this->request->getPost('reversed_meaning')
        ]);

        return redirect()->to('/cards')->with('success', 'Card updated successfully.');
    }

    // Delete card
    public function destroy($id)
    {
        $this->tarotCardModel->delete($id);
        return redirect()->to('/cards')->with('success', 'Card deleted successfully.');
    }
}
