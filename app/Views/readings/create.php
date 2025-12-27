<?= $this->extend('layouts/client_layout') ?>
<?= $this->section('content') ?>

<h3>Create Reading</h3>
<?= view('templates/alerts') ?>

<div class="row">
    <form action="/readings/store" method="POST">
        <?= csrf_field() ?>

        <!-- User/Client Selection -->
        <div class="mb-3">
            <label for="user_id" class="form-label">Client</label>
            <select name="user_id" id="user_id" class="form-select">
                <option value="">Anonymous</option>
                <?php foreach ($users as $user): ?>
                    <option value="<?= $user['id'] ?>"><?= esc($user['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Reading Type -->
        <div class="mb-3">
            <label for="reading_type" class="form-label">Reading Type</label>
            <select name="reading_type" id="reading_type" class="form-select">
                <option value="Three-card">Three-card</option>
                <option value="Celtic cross">Celtic Cross</option>
                <option value="One-card">One-card</option>
            </select>
        </div>

        <!-- Reading Date -->
        <div class="mb-3">
            <label for="reading_date" class="form-label">Reading Date</label>
            <input type="datetime-local" name="reading_date" id="reading_date" class="form-control" value="<?= date('Y-m-d\TH:i') ?>">
        </div>

        <!-- General Notes -->
        <div class="mb-3">
            <label for="notes" class="form-label">Reading Notes</label>
            <textarea name="notes" id="notes" class="form-control" rows="3"></textarea>
        </div>

        <!-- Card Selection -->
        <div id="cards-container">
            <h5>Cards</h5>

            <div class="card-entry mb-3">
                <select name="cards[0][card_id]" class="form-select mb-2">
                    <?php foreach ($cards as $card): ?>
                        <option value="<?= $card['id'] ?>"><?= esc($card['name']) ?> (<?= $card['arcana_type'] ?>)</option>
                    <?php endforeach; ?>
                </select>

                <select name="cards[0][position]" class="form-select mb-2 position-select">
                    <option value="Past">Past</option>
                    <option value="Present">Present</option>
                    <option value="Future">Future</option>
                    <option value="Position1">Position 1</option>
                    <option value="Position2">Position 2</option>
                </select>

                <select name="cards[0][orientation]" class="form-select mb-2">
                    <option value="upright">Upright</option>
                    <option value="reversed">Reversed</option>
                </select>

                <input type="text" name="cards[0][notes]" class="form-control mb-2" placeholder="Card notes (optional)">
            </div>
        </div>

        <!-- Add More Card Button -->
        <button type="button" id="add-card" class="btn btn-secondary mb-3">Add Another Card</button>

        <button type="submit" class="btn btn-primary">Save Reading</button>
    </form>
</div>

<!-- Dynamic JS -->
<script>
    let cardIndex = 1;

    // Add another card
    document.getElementById('add-card').addEventListener('click', () => {
        const container = document.getElementById('cards-container');
        const template = document.querySelector('.card-entry');
        const clone = template.cloneNode(true);

        // Update input names with new index
        clone.querySelectorAll('select, input').forEach(el => {
            const name = el.getAttribute('name');
            const newName = name.replace(/\d+/, cardIndex);
            el.setAttribute('name', newName);
            if (el.tagName === 'INPUT') el.value = '';
            if (el.tagName === 'SELECT') el.selectedIndex = 0;
        });

        container.appendChild(clone);
        cardIndex++;
    });

    // Optional: change positions based on reading type
    document.getElementById('reading_type').addEventListener('change', function() {
        const type = this.value;
        const positionSelects = document.querySelectorAll('.position-select');

        positionSelects.forEach(select => {
            select.innerHTML = ''; // clear current options

            if (type === 'One-card') {
                select.innerHTML = '<option value="Center">Center</option>';
            } else if (type === 'Three-card') {
                select.innerHTML = `
                <option value="Past">Past</option>
                <option value="Present">Present</option>
                <option value="Future">Future</option>
            `;
            } else if (type === 'Celtic cross') {
                for (let i = 1; i <= 10; i++) {
                    select.innerHTML += `<option value="Position${i}">Position ${i}</option>`;
                }
            }
        });
    });
</script>

<?= $this->endSection() ?>