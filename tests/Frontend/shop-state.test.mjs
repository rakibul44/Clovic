import test from "node:test";
import assert from "node:assert/strict";
import { variantPicker, checkoutForm } from "../../resources/js/shop-state.js";

const variants = [
    { id: 1, size: "M", color: "Navy", stock: 3, sku: "N-M" },
    { id: 2, size: "L", color: "Navy", stock: 0, sku: "N-L" },
    { id: 3, size: "L", color: "Sand", stock: 2, sku: "S-L" },
];

test("a required size and color resolve one exact variant", () => {
    const picker = variantPicker(variants);
    picker.init();
    assert.equal(picker.selected, null);
    picker.color = "Navy";
    assert.equal(picker.selected, null);
    picker.size = "M";
    assert.equal(picker.selected.id, 1);
    assert.equal(picker.selected.stock, 3);
});
test("unavailable sizes cannot be chosen for a color and stale choices reset", () => {
    const picker = variantPicker(variants);
    picker.init();
    picker.chooseColor("Navy");
    assert.equal(picker.sizeAvailable("L"), false);
    picker.size = "M";
    picker.quantity = 2;
    picker.chooseColor("Sand");
    assert.equal(picker.size, "");
    assert.equal(picker.quantity, 1);
    assert.equal(picker.sizeAvailable("L"), true);
});
test("a single color is preselected while size still requires a choice", () => {
    const picker = variantPicker(variants.slice(0, 2));
    picker.init();
    assert.equal(picker.color, "Navy");
    assert.equal(picker.size, "");
    assert.equal(picker.selected, null);
});
test("checkout preview uses supplied region fees and coupon amount", () => {
    const checkout = checkoutForm({
        area: "inside",
        inside: 8000,
        outside: 15000,
        subtotal: 240000,
        discount: 24000,
    });
    assert.equal(checkout.total, 224000);
    assert.equal(checkout.money(checkout.total), "TK 2,240");
    checkout.area = "outside";
    assert.equal(checkout.total, 231000);
    assert.equal(checkout.money(129955), "TK 1,299.55");
});
test("free shipping preview uses the discounted subtotal and exact threshold", () => {
    const checkout = checkoutForm({
        area: "outside",
        inside: 8000,
        outside: 15000,
        subtotal: 100000,
        discount: 5000,
        freeThreshold: 95000,
    });
    assert.equal(checkout.delivery, 0);
    checkout.discount = 5001;
    assert.equal(checkout.delivery, 15000);
    checkout.freeThreshold = 0;
    assert.equal(checkout.delivery, 15000);
});
