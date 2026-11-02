import Alpine from "alpinejs";
import { variantPicker, checkoutForm } from "./shop-state.js";
import {
    createIcons,
    Bell,
    Search,
    UserRound,
    Heart,
    ShoppingBag,
    Menu,
    Truck,
    Banknote,
    Ruler,
    MessageCircle,
    Camera,
    Globe,
    X,
    ChevronDown,
    House,
    LayoutGrid,
    SlidersHorizontal,
    ArrowUpRight,
    ShieldCheck,
    ArrowLeft,
    Package,
    ChartNoAxesCombined,
} from "lucide";

window.trapDialogFocus = (event, dialog) => {
    const controls = [
        ...dialog.querySelectorAll(
            'a[href],button:not([disabled]),input:not([type=hidden]),select,textarea,[tabindex="0"]',
        ),
    ].filter((element) => element.offsetParent !== null);
    if (!controls.length) return;
    const first = controls[0],
        last = controls.at(-1);
    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
};
const initial = JSON.parse(
    document.getElementById("storefront-state")?.textContent ||
        '{"cart":{"items":[],"count":0,"subtotal":0,"subtotal_label":"TK 0"},"wishlist":[]}',
);
const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content;
const api = async (url, data) => {
    const response = await fetch(url, {
        method: "POST",
        headers: {
            Accept: "application/json",
            "X-CSRF-TOKEN": csrf(),
            "X-Requested-With": "XMLHttpRequest",
        },
        body: data,
        credentials: "same-origin",
    });
    if (response.status === 401) {
        window.location.assign("/login");
        throw new Error("Please sign in to save your wishlist.");
    }
    const result = await response.json().catch(() => ({
        message:
            "The request could not be completed. Please refresh and try again.",
    }));
    if (!response.ok)
        throw new Error(
            Object.values(result.errors || {}).flat()[0] ||
                result.message ||
                "Please try again.",
        );
    return result;
};
Alpine.store("cart", {
    ...initial.cart,
    saved: initial.wishlist,
    busy: false,
    error: "",
    message: "",
    timer: null,
    hydrate(cart) {
        Object.assign(this, cart);
    },
    notify(message) {
        this.message = message;
        clearTimeout(this.timer);
        this.timer = setTimeout(() => (this.message = ""), 6500);
    },
    async change(item, quantity) {
        if (this.busy) return;
        this.busy = true;
        this.error = "";
        const data = new FormData();
        data.set("variant_id", item.id);
        data.set("quantity", quantity);
        try {
            const result = await api("/cart", data);
            this.hydrate(result.cart);
            this.notify(
                quantity
                    ? "Shopping bag updated."
                    : "Item removed from your bag.",
            );
        } catch (error) {
            this.error = error.message;
        } finally {
            this.busy = false;
        }
    },
});
Alpine.data("storefront", () => ({
    mobile: false,
    search: false,
    bag: false,
    categories: false,
    announcement: true,
    query: "",
    suggestions: [],
    loading: false,
    searched: false,
    requestNumber: 0,
    opener: null,
    init() {
        try {
            this.announcement =
                sessionStorage.getItem("clovic-announcement") !== "dismissed";
        } catch {}
        this.$watch("bag", (value) => {
            this.lockPage();
            if (!value) this.restoreFocus();
        });
        this.$watch("mobile", (value) => {
            this.lockPage();
            if (!value) this.restoreFocus();
        });
        window.addEventListener("keydown", (event) => {
            if (
                event.key === "/" &&
                !event.ctrlKey &&
                !event.metaKey &&
                !["INPUT", "TEXTAREA", "SELECT"].includes(
                    document.activeElement?.tagName,
                )
            ) {
                event.preventDefault();
                this.openSearch();
            }
        });
    },
    dismissAnnouncement() {
        this.announcement = false;
        try {
            sessionStorage.setItem("clovic-announcement", "dismissed");
        } catch {}
    },
    lockPage() {
        document.body.style.overflow = this.bag || this.mobile ? "hidden" : "";
        const main = document.getElementById("main");
        if (main) main.inert = this.bag || this.mobile;
    },
    restoreFocus() {
        this.$nextTick(() => {
            if (this.opener?.isConnected) this.opener.focus();
        });
    },
    openBag() {
        this.opener = document.activeElement;
        this.search = false;
        this.categories = false;
        this.mobile = false;
        this.bag = true;
        this.$nextTick(() => this.$refs.closeBag.focus());
    },
    openMenu() {
        this.opener = document.activeElement;
        this.search = false;
        this.mobile = true;
        this.$nextTick(() => this.$refs.closeMenu.focus());
    },
    openSearch() {
        this.search = true;
        this.categories = false;
        this.$nextTick(() => this.$refs.searchInput.focus());
    },
    clearSearch() {
        this.query = "";
        this.suggestions = [];
        this.requestNumber++;
        this.loading = false;
        this.searched = false;
        this.$refs.searchInput.focus();
    },
    closePanels() {
        this.categories = false;
        this.search = false;
        this.bag = false;
        this.mobile = false;
    },
    async suggest() {
        const number = ++this.requestNumber;
        const query = this.query.trim();
        this.searched = false;
        if (query.length < 2) {
            this.suggestions = [];
            this.loading = false;
            return;
        }
        this.loading = true;
        try {
            const response = await fetch(
                "/search-suggestions?q=" + encodeURIComponent(query),
                { headers: { Accept: "application/json" } },
            );
            const items = response.ok ? await response.json() : [];
            if (number === this.requestNumber) {
                this.suggestions = items;
                this.searched = true;
            }
        } catch {
            if (number === this.requestNumber) {
                this.suggestions = [];
                this.searched = true;
            }
        } finally {
            if (number === this.requestNumber) this.loading = false;
        }
    },
}));
Alpine.data("adminWorkspace", () => ({
    sidebar: window.innerWidth >= 1024,
    opener: null,
    init() {
        this.$watch("sidebar", (value) => {
            const modal = value && window.innerWidth < 1024;
            document.body.style.overflow = modal ? "hidden" : "";
            const main = document.getElementById("main");
            if (main) main.inert = modal;
            if (modal) this.$nextTick(() => this.$refs.adminClose.focus());
            else if (!value) this.$nextTick(() => this.opener?.focus());
        });
        window
            .matchMedia("(min-width:1024px)")
            .addEventListener("change", (event) => {
                this.sidebar = event.matches;
            });
    },
    toggleSidebar() {
        this.opener = document.activeElement;
        this.sidebar = !this.sidebar;
    },
}));
Alpine.data("quickProduct", () => ({
    quick: false,
    init() {
        this.$watch("quick", (value) => {
            document.body.style.overflow = value ? "hidden" : "";
            this.$nextTick(() =>
                value
                    ? this.$refs.quickClose.focus()
                    : this.$refs.quickTrigger?.focus(),
            );
        });
    },
}));
Alpine.data("variantPicker", variantPicker);

Alpine.data("checkoutForm", checkoutForm);

document.addEventListener("submit", async (event) => {
    const form = event.target;
    if (form.matches("[data-cart-form]")) {
        event.preventDefault();
        const cart = Alpine.store("cart");
        if (cart.busy) return;
        const feedback = form.querySelector("[data-form-feedback]");
        if (feedback) feedback.textContent = "";
        const data = new FormData(form, event.submitter);
        if (!data.get("variant_id")) {
            if (feedback)
                feedback.textContent = "Choose a color and size first.";
            return;
        }
        cart.busy = true;
        cart.error = "";
        try {
            const result = await api(form.action, data);
            cart.hydrate(result.cart);
            form.dispatchEvent(
                new CustomEvent("cart-added", { bubbles: true }),
            );
            cart.notify("Added to your shopping bag.");
            if (result.redirect) window.location.assign(result.redirect);
        } catch (error) {
            if (feedback) feedback.textContent = error.message;
            else cart.notify(error.message);
        } finally {
            cart.busy = false;
        }
    }
    if (form.matches("[data-wishlist-form]")) {
        event.preventDefault();
        const button = form.querySelector("button");
        if (button.disabled) return;
        button.disabled = true;
        try {
            const result = await api(form.action, new FormData(form));
            const cart = Alpine.store("cart");
            cart.saved = result.saved
                ? [...new Set([...cart.saved, result.product_id])]
                : cart.saved.filter((id) => id !== result.product_id);
            cart.notify(
                result.saved
                    ? "Saved to your wishlist."
                    : "Removed from your wishlist.",
            );
        } catch (error) {
            Alpine.store("cart").notify(error.message);
        } finally {
            button.disabled = false;
        }
    }
});
document.addEventListener("click", (event) => {
    const button = event.target.closest("[data-confirm]");
    if (button && !window.confirm(button.dataset.confirm))
        event.preventDefault();
});
window.Alpine = Alpine;
Alpine.start();
createIcons({
    icons: {
        Bell,
        Search,
        UserRound,
        Heart,
        ShoppingBag,
        Menu,
        Truck,
        Banknote,
        Ruler,
        MessageCircle,
        Instagram: Camera,
        Facebook: Globe,
        X,
        ChevronDown,
        House,
        LayoutGrid,
        SlidersHorizontal,
        ArrowUpRight,
        ShieldCheck,
        ArrowLeft,
        Package,
        ChartNoAxesCombined,
    },
});
