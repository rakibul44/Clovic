export const variantPicker = (variants) => ({
    variants,
    color: "",
    size: "",
    quantity: 1,
    init() {
        if (this.colors.length === 1) this.color = this.colors[0];
        if (this.$watch)
            this.$watch("selected", (variant) =>
                this.$dispatch("variant-selected", {
                    productId: variant?.product_id,
                    image: variant?.image,
                }),
            );
    },
    get colors() {
        return [...new Set(this.variants.map((variant) => variant.color))];
    },
    get sizes() {
        return [...new Set(this.variants.map((variant) => variant.size))];
    },
    get selected() {
        return (
            this.variants.find(
                (variant) =>
                    variant.color === this.color && variant.size === this.size,
            ) || null
        );
    },
    chooseColor(color) {
        this.color = color;
        if (!this.sizeAvailable(this.size)) this.size = "";
        this.quantity = 1;
    },
    sizeAvailable(size) {
        return this.variants.some(
            (variant) =>
                variant.size === size &&
                (!this.color || variant.color === this.color) &&
                variant.stock > 0,
        );
    },
});

export const checkoutForm = (data) => ({
    ...data,
    busy: false,
    get delivery() {
        if (
            this.freeThreshold > 0 &&
            this.subtotal - this.discount >= this.freeThreshold
        )
            return 0;
        return this.area === "outside" ? this.outside : this.inside;
    },
    get total() {
        return this.subtotal - this.discount + this.delivery;
    },
    money(amount) {
        return (
            "TK " +
            (amount / 100).toLocaleString("en-BD", {
                minimumFractionDigits: amount % 100 ? 2 : 0,
                maximumFractionDigits: 2,
            })
        );
    },
});
