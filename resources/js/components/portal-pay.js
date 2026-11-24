/**
 * Tenant Pay screen: pick invoices (oldest preselected) or type an amount,
 * then pay by card (Paystack) or bank transfer. Amounts are kept in minor
 * units (kobo), which is what both forms submit.
 */
export default function portalPay({ invoices = [], cardEnabled = false }) {
    return {
        invoices,
        selected: invoices.length ? [String(invoices[0].id)] : [],
        custom: invoices.length === 0,
        customDisplay: '',
        method: cardEnabled ? 'card' : 'transfer',

        get selectedTotal() {
            return this.invoices
                .filter((invoice) => this.selected.includes(String(invoice.id)))
                .reduce((sum, invoice) => sum + invoice.outstanding, 0);
        },

        get amount() {
            if (!this.custom) {
                return this.selectedTotal;
            }
            const cleaned = this.customDisplay.replace(/[^0-9.]/g, '');
            const [whole, ...rest] = cleaned.split('.');
            if (whole === '' && rest.length === 0) {
                return 0;
            }
            const fraction = rest.join('').slice(0, 2);
            return Math.round(Number(`${whole || '0'}.${fraction || '0'}`) * 100);
        },

        get invoiceIds() {
            return this.custom ? [] : this.selected;
        },

        format(minor) {
            return new Intl.NumberFormat('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(minor / 100);
        },
    };
}
