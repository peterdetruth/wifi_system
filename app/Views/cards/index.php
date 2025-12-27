<?= $this->extend('layouts/client_layout') ?>
<?= $this->section('content') ?>

<h3>Tarot Cards</h3>
<?= view('templates/alerts') ?>

<a href="/cards/create" class="btn btn-primary mb-3">Add New Card</a>

<table class="table table-striped">
    <thead>
        <tr>
            <th>Name</th>
            <th>Arcana Type</th>
            <th>Suit</th>
            <th>Upright Meaning</th>
            <th>Reversed Meaning</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!empty($cards)): ?>
            <?php foreach ($cards as $card): ?>
                <tr>
                    <td><?= esc($card['name']) ?></td>
                    <td><?= esc($card['arcana_type']) ?></td>
                    <td><?= esc($card['suit']) ?: 'N/A' ?></td>
                    <td><?= esc($card['upright_meaning']) ?></td>
                    <td><?= esc($card['reversed_meaning']) ?></td>
                    <td>
                        <a href="/cards/edit/<?= $card['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                        <form action="/cards/destroy/<?= $card['id'] ?>" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure?');">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="6" class="text-center">No cards found.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<?= $this->endSection() ?>
