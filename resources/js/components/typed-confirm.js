/** Enables a destructive action only once the user has typed the confirmation word. */
export default function typedConfirm(word = '') {
    return {
        typed: '',
        get confirmed() {
            return word === '' || this.typed.trim().toLowerCase() === word.toLowerCase();
        },
    };
}
