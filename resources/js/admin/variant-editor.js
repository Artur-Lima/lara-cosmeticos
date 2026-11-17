// Editor de variações do cadastro de produto (admin).
let seq = 0;

const blank = () => ({
    id: null, sku: '', label: '', barcode: '', volume_ml: null, shade: '',
    color_hex: null, price: '', compare_at_price: null, stock: 0, weight_g: null, is_active: true,
});

export default (initial = [], defaultIndex = 0, errors = {}) => ({
    rows: (initial.length ? initial : [blank()]).map((row) => ({
        ...blank(),
        ...row,
        // Checkbox vindo de old() chega como "1"/"0".
        is_active: row.is_active === undefined ? true : [true, 1, '1', 'true'].includes(row.is_active),
        _key: ++seq,
    })),
    defaultIndex: Number(defaultIndex) || 0,
    errors,

    add() {
        this.rows.push({ ...blank(), _key: ++seq });
    },

    remove(i) {
        this.rows.splice(i, 1);
        if (this.defaultIndex === i) this.defaultIndex = 0;
        else if (this.defaultIndex > i) this.defaultIndex--;
        // Índices mudaram: erros do servidor por posição deixam de valer.
        this.errors = {};
    },

    err(i, field) {
        return this.errors[`variants.${i}.${field}`] ?? '';
    },
});
