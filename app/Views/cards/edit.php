<?= $this->extend('layouts/client_layout') ?>
<?= $this->section('content') ?>

<h3>Edit Tarot Card</h3>
<?= view('templates/alerts') ?>

<form action="/cards/update/<?= $card['id'] ?>" method="POST">
    <?= csrf_field() ?>

    <div class="mb-3">
        <label for="name" class="form-label">Card Name</label>
        <input
            type="text"
            name="name"
            id="name"
            class="form-control"
            value="<?= esc($card['name']) ?>"
            required
        >
    </div>

    <div class="mb-3">
        <label for="arcana_type" class="form-label">Arcana Type</label>
        <select name="arcana_type" id="arcana_type" class="form-select" required>
            <option value="Major" <?= $card['arcana_type'] === 'Major' ? 'selected' : '' ?>>
                Major
            </option>
            <option value="Minor" <?= $card['arcana_type'] === 'Minor' ? 'selected' : '' ?>>
                Minor
            </option>
        </select>
    </div>

    <div class="mb-3">
        <label for="suit" class="form-label">Suit (Minor Arcana only)</label>
        <select name="suit" id="suit" class="form-select">
            <option value="">N/A</option>
            <?php
                $suits = ['Cups', 'Wands', 'Swords', 'Pentacles'];
                foreach ($suits as $suit):
            ?>
                <option value="<?= $suit ?>" <?= $card['suit'] === $suit ? 'selected' : '' ?>>
                    <?= $suit ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="mb-3">
        <label for="upright_meaning" class="form-label">Upright Meaning</label>
        <textarea
            name="upright_meaning"
            id="upright_meaning"
            class="form-control"
            rows="4"
        ><?= esc($card['upright_meaning']) ?></textarea>
    </div>

    <div class="mb-3">
        <label for="reversed_meaning" class="form-label">Reversed Meaning</label>
        <textarea
            name="reversed_meaning"
            id="reversed_meaning"
            class="form-control"
            rows="4"
        ><?= esc($card['reversed_meaning']) ?></textarea>
    </div>

    <button type="submit" class="btn btn-primary">Update Card</button>
    <a href="/cards" class="btn btn-secondary">Cancel</a>
</form>

<?= $this->endSection() ?>
