/**
 * Shows a major-unit amount with thousands separators and keeps the minor
 * units (kobo) in a hidden field, which is what the form submits.
 */
export default function moneyInput(initialMinor = null) {
    return {
        minor: initialMinor === null || initialMinor === '' ? '' : String(initialMinor),
        display: '',

        init() {
            this.display = this.minor === '' ? '' : this.format(Number(this.minor) / 100);
        },

        format(major) {
            return new Intl.NumberFormat('en-NG', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            }).format(major);
        },

        onInput() {
            const cleaned = this.display.replace(/[^0-9.]/g, '');
            const [whole, ...rest] = cleaned.split('.');
            const fraction = rest.join('').slice(0, 2);
            if (whole === '' && fraction === '') {
                this.minor = '';
                return;
            }
            const major = Number(`${whole || '0'}.${fraction || '0'}`);
            this.minor = String(Math.round(major * 100));
        },

        onBlur() {
            this.display = this.minor === '' ? '' : this.format(Number(this.minor) / 100);
        },
    };
}
