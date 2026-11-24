@props([
    'id' => 'admin-confirm-dialog',
    'formId' => 'admin-confirm-form',
    'titleId' => 'admin-confirm-title',
    'descId' => 'admin-confirm-desc',
    'defaultTitle' => 'Konfirmasi tindakan',
    'submitLabel' => 'Ya, lanjutkan',
    'danger' => true,
])

<dialog
    class="admin-confirm-dialog"
    id="{{ $id }}"
    aria-labelledby="{{ $titleId }}"
    aria-describedby="{{ $descId }}"
>
    <form method="POST" id="{{ $formId }}">
        @csrf
        <input type="hidden" name="_method" id="{{ $id }}-method" value="DELETE">
        {{ $hiddenFields ?? '' }}
        <h3 id="{{ $titleId }}">{{ $defaultTitle }}</h3>
        <p id="{{ $descId }}">{{ $slot }}</p>
        <div class="admin-dialog-actions">
            <button class="admin-btn-outline" type="button" data-dialog-close="{{ $id }}">Batal</button>
            <button
                class="{{ $danger ? 'admin-btn-danger' : 'admin-btn-primary' }}"
                type="submit"
                id="{{ $id }}-submit"
            >{{ $submitLabel }}</button>
        </div>
    </form>
</dialog>

@pushOnce('scripts')
<script>
    document.addEventListener('click', (event) => {
        const closeTrigger = event.target.closest('[data-dialog-close]');
        if (closeTrigger) {
            const targetDialog = document.getElementById(closeTrigger.dataset.dialogClose);
            if (targetDialog && typeof targetDialog.close === 'function') {
                targetDialog.close();
            }
            return;
        }

        if (event.target.tagName === 'DIALOG' && event.target.open) {
            const rect = event.target.getBoundingClientRect();
            const clickedInside = event.clientX >= rect.left
                && event.clientX <= rect.right
                && event.clientY >= rect.top
                && event.clientY <= rect.bottom;
            if (!clickedInside) {
                event.target.close();
            }
        }
    });

    document.addEventListener('submit', (event) => {
        if (event.target.closest('dialog.admin-confirm-dialog')) {
            const submitBtn = event.target.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
            }
        }
    });
</script>
@endPushOnce
