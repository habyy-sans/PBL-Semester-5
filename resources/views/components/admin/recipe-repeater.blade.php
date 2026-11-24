@props([
    'inputName' => 'items',
    'ingredients' => [],
    'required' => true,
    'addButtonLabel' => 'Tambah Bahan Baku',
    'emptyText' => 'Belum ada bahan baku ditambahkan.',
])

<div class="admin-recipe-repeater">
    <div class="admin-recipe-list" x-show="rows.length > 0">
        <template x-for="(row, index) in rows" :key="row.key">
            <div class="admin-recipe-row">
                <div class="admin-recipe-cell is-ingredient">
                    <label class="admin-recipe-label" :for="'{{ $inputName }}-bahan-' + index">
                        Bahan baku <span class="required-mark">*</span>
                    </label>
                    <select
                        :id="'{{ $inputName }}-bahan-' + index"
                        :name="isEnabled ? '{{ $inputName }}[' + index + '][bahan_id]' : null"
                        x-model="row.bahan_id"
                        @change="clearRowError(index, 'bahan_id')"
                        :disabled="!isEnabled"
                        :required="isEnabled && {{ $required ? 'true' : 'false' }}"
                        :aria-invalid="rowError(index, 'bahan_id') ? 'true' : 'false'"
                        data-recipe-bahan-select
                    >
                        <option value="">Pilih bahan baku</option>
                        @foreach ($ingredients as $ing)
                            <option
                                value="{{ $ing['bahan_id'] }}"
                                :disabled="isIngredientTaken('{{ $ing['bahan_id'] }}', index)"
                            >
                                {{ $ing['nama_bahan'] }} ({{ $ing['satuan'] }})
                            </option>
                        @endforeach
                    </select>
                    <span class="admin-field-error" x-show="rowError(index, 'bahan_id')" x-text="rowError(index, 'bahan_id')"></span>
                </div>

                <div class="admin-recipe-cell is-qty">
                    <label class="admin-recipe-label" :for="'{{ $inputName }}-qty-' + index">
                        Takaran <span class="required-mark">*</span>
                    </label>
                    <div class="admin-input-with-unit">
                        <input
                            :id="'{{ $inputName }}-qty-' + index"
                            type="number"
                            min="0.001"
                            max="999999999.999"
                            step="any"
                            placeholder="0"
                            :name="isEnabled ? '{{ $inputName }}[' + index + '][jumlah_bahan]' : null"
                            x-model="row.jumlah_bahan"
                            @input="clearRowError(index, 'jumlah_bahan')"
                            :disabled="!isEnabled"
                            :required="isEnabled && {{ $required ? 'true' : 'false' }}"
                            :aria-invalid="rowError(index, 'jumlah_bahan') ? 'true' : 'false'"
                        >
                        <span class="admin-unit-chip" x-text="rowUnit(row)"></span>
                    </div>
                    <span class="admin-field-error" x-show="rowError(index, 'jumlah_bahan')" x-text="rowError(index, 'jumlah_bahan')"></span>
                </div>

                <div class="admin-recipe-cell is-cost">
                    <span class="admin-recipe-label">Est. Biaya</span>
                    <strong class="admin-recipe-row-cost tabular-nums" x-text="formatRupiah(rowCost(row))">Rp0</strong>
                </div>

                <div class="admin-recipe-cell is-remove">
                    <button
                        type="button"
                        class="admin-btn-ghost admin-btn-sm is-danger admin-recipe-remove"
                        @click="removeRow(index)"
                        :disabled="!isEnabled || (required && rows.length <= 1)"
                        :title="required && rows.length <= 1 ? 'Minimal 1 baris bahan' : 'Hapus bahan'"
                        aria-label="Hapus baris bahan baku"
                    >
                        <x-icon name="trash-2" size="1em" />
                        <span class="admin-recipe-remove-label">Hapus</span>
                    </button>
                </div>
            </div>
        </template>
    </div>

    <div class="admin-recipe-empty-hint" x-show="rows.length === 0">
        <span>{{ $emptyText }}</span>
    </div>

    <div class="admin-recipe-repeater-footer">
        <button
            type="button"
            class="admin-btn-outline admin-btn-sm"
            @click="addRow()"
            :disabled="!isEnabled"
        >
            <x-icon name="plus" size="1em" />
            <span>{{ $addButtonLabel }}</span>
        </button>

        <span class="admin-field-help tabular-nums">
            <strong x-text="validRows().length">0</strong> bahan baku siap disimpan
        </span>
    </div>
</div>

@pushOnce('scripts')
<script>
    window.createRecipeState = (config) => ({
        inputName: config.inputName || 'items',
        ingredients: config.ingredients || [],
        variants: config.variants || [],
        selectedVarianId: config.selectedVarianId ? String(config.selectedVarianId) : '',
        isLockedVariant: Boolean(config.isLockedVariant),
        required: config.required !== false,
        isEnabled: config.isEnabled !== false,
        sellingPrice: Number(config.sellingPrice || 0),
        rowCounter: 0,
        serverErrors: config.serverErrors || {},
        rows: [],

        init() {
            const initial = Array.isArray(config.initialRows) ? config.initialRows : [];
            if (initial.length > 0) {
                this.rows = initial.map((r) => ({
                    key: `row-${++this.rowCounter}`,
                    bahan_id: r.bahan_id !== undefined && r.bahan_id !== null ? String(r.bahan_id) : '',
                    jumlah_bahan: r.jumlah_bahan !== undefined && r.jumlah_bahan !== null ? String(r.jumlah_bahan) : '',
                }));
            } else if (this.required) {
                this.rows = [{ key: `row-${++this.rowCounter}`, bahan_id: '', jumlah_bahan: '' }];
            }

            if (this.variants.length > 0 && this.selectedVarianId) {
                const current = this.currentVariant();
                if (current) {
                    this.sellingPrice = Number(current.selling_price || 0);
                }
            }
        },

        findIngredient(bahanId) {
            if (!bahanId) {
                return null;
            }
            return this.ingredients.find((item) => String(item.bahan_id) === String(bahanId)) || null;
        },

        currentVariant() {
            if (!this.selectedVarianId) {
                return null;
            }
            return this.variants.find((v) => String(v.varian_id) === String(this.selectedVarianId)) || null;
        },

        onVariantChange() {
            const variant = this.currentVariant();
            if (!variant) {
                this.sellingPrice = 0;
                this.rows = this.required
                    ? [{ key: `row-${++this.rowCounter}`, bahan_id: '', jumlah_bahan: '' }]
                    : [];
                this.serverErrors = {};
                return;
            }

            this.sellingPrice = Number(variant.selling_price || 0);
            const existing = Array.isArray(variant.existing_items) ? variant.existing_items : [];
            if (existing.length > 0) {
                this.rows = existing.map((item) => ({
                    key: `row-${++this.rowCounter}`,
                    bahan_id: String(item.bahan_id),
                    jumlah_bahan: String(item.jumlah_bahan),
                }));
            } else {
                this.rows = [{ key: `row-${++this.rowCounter}`, bahan_id: '', jumlah_bahan: '' }];
            }
            this.serverErrors = {};
        },

        isIngredientTaken(bahanId, currentIndex) {
            return this.rows.some((row, idx) => idx !== currentIndex && String(row.bahan_id) === String(bahanId));
        },

        rowUnit(row) {
            return this.findIngredient(row.bahan_id)?.satuan || 'satuan';
        },

        rowCost(row) {
            const ing = this.findIngredient(row.bahan_id);
            const qty = parseFloat(row.jumlah_bahan);
            if (!ing || !Number.isFinite(qty) || qty <= 0) {
                return 0;
            }
            return qty * Number(ing.unit_cost || 0);
        },

        validRows() {
            return this.rows
                .map((row) => ({
                    ing: this.findIngredient(row.bahan_id),
                    qty: parseFloat(row.jumlah_bahan),
                }))
                .filter((item) => item.ing && Number.isFinite(item.qty) && item.qty > 0);
        },

        totalHpp() {
            return this.rows.reduce((sum, row) => sum + this.rowCost(row), 0);
        },

        effectiveSellingPrice() {
            const variant = this.currentVariant();
            return variant ? Number(variant.selling_price || 0) : Number(this.sellingPrice || 0);
        },

        estimatedProfit() {
            return this.effectiveSellingPrice() - this.totalHpp();
        },

        estimatedMargin() {
            const sell = this.effectiveSellingPrice();
            if (sell <= 0) {
                return 0;
            }
            return (this.estimatedProfit() / sell) * 100;
        },

        formatRupiah(value) {
            return 'Rp' + Math.round(Number(value || 0)).toLocaleString('id-ID');
        },

        formatQty(value) {
            const num = Number(value || 0);
            if (!Number.isFinite(num)) {
                return '0';
            }
            return num.toLocaleString('id-ID', { maximumFractionDigits: 3 });
        },

        rowError(index, field) {
            const key = `${this.inputName}.${index}.${field}`;
            const list = this.serverErrors[key];
            return Array.isArray(list) && list.length > 0 ? list[0] : '';
        },

        clearRowError(index, field) {
            const key = `${this.inputName}.${index}.${field}`;
            if (this.serverErrors[key]) {
                delete this.serverErrors[key];
            }
        },

        addRow() {
            this.rows.push({
                key: `row-${++this.rowCounter}`,
                bahan_id: '',
                jumlah_bahan: '',
            });

            this.$nextTick(() => {
                const container = this.$el?.closest('.admin-recipe-repeater') || this.$root || document;
                const selects = container.querySelectorAll('[data-recipe-bahan-select]');
                selects[selects.length - 1]?.focus();
            });
        },

        removeRow(index) {
            if (this.required && this.rows.length <= 1) {
                this.rows[0].bahan_id = '';
                this.rows[0].jumlah_bahan = '';
                return;
            }
            this.rows.splice(index, 1);
        },
    });
</script>
@endPushOnce
