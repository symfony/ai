import { Controller } from '@hotwired/stimulus';
import Typed from 'typed.js';

export default class extends Controller {
    static values = {
        strings: Array,
    };

    connect() {
        new Typed(this.element, {
            strings: this.stringsValue,
            typeSpeed: 0,
            showCursor: false,
            contentType: 'html',
        });
    }
}
