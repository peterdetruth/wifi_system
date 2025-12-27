<?= $this->extend('layouts/client_layout') ?>
<?= $this->section('content') ?>

<h3>Add Tarot Card</h3>
<?= view('templates/alerts') ?>

<form action="/cards/store" method="POST">
    <?= csrf_field() ?>

    <div class="mb-3">
        <label for="name" class="form-label">Card Name</label>
        <input type="text" name="name" id="name" class="form-control" required>
    </div>

    <div class="mb-3">
        <label for="arcana_type" class="form-label">Arcana Type</label>
        <select name="arcana_type" id="arcana_type" class="form-select" required>
            <option value="Major">Major</option>
            <option value="Minor">Minor</option>
        </select>
    </div>

    <div class="mb-3">
        <label for="suit" class="form-label">Suit (for Minor Arcana)</label>
        <select name="suit" id="suit" class="form-select">
            <option value="">N/A</option>
            <option value="Cups">Cups</option>
            <option value="Wands">Wands</option>
            <option value="Swords">Swords</option>
            <option value="Pentacles">Pentacles</option>
        </select>
    </div>

    <div class="mb-3">
        <label for="upright_meaning" class="form-label">Upright Meaning</label>
        <textarea name="upright_meaning" id="upright_meaning" class="form-control" rows="3"></textarea>
    </div>

    <div class="mb-3">
        <label for="reversed_meaning" class="form-label">Reversed Meaning</label>
        <textarea name="reversed_meaning" id="reversed_meaning" class="form-control" rows="3"></textarea>
    </div>

    <button type="submit" class="btn btn-primary">Add Card</button>
</form>

<?= $this->endSection() ?>
