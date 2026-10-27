/* Generation version 1. Keep deterministic rounding in sync with ClientTheme and shared fixtures. */
(() => {
    'use strict';
    function mix(source, target, percent) {
        let result = '#';
        for (let i = 1; i < 7; i += 2) {
            const value = Math.floor((parseInt(source.slice(i, i + 2), 16) * (100 - percent) + parseInt(target.slice(i, i + 2), 16) * percent) / 100 + 0.5);
            result += value.toString(16).padStart(2, '0');
        }
        return result;
    }
    function luminance(colour) {
        const channels = colour.slice(1).match(/../g).map(hex => {
            const value = parseInt(hex, 16) / 255;
            return value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4;
        });
        return channels[0] * 0.2126 + channels[1] * 0.7152 + channels[2] * 0.0722;
    }
    function contrast(one, two) {
        const a = luminance(one), b = luminance(two);
        return (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05);
    }
    function readable(source, surfaces, target, minimum) {
        for (let percent = 0; percent <= 100; percent++) {
            const candidate = mix(source, target, percent);
            if (Math.min(...surfaces.map(surface => contrast(candidate, surface))) >= minimum) return candidate;
        }
        throw new Error('Generated surfaces cannot support contrast.');
    }
    function generate(source) {
        if (!/^#[0-9a-f]{6}$/i.test(source)) throw new Error('Enter a colour as #RRGGBB.');
        const palettes = {};
        for (const scheme of ['light', 'dark']) {
            const dark = scheme === 'dark', target = dark ? '#ffffff' : '#000000';
            const background = mix(source, dark ? '#0b0f14' : '#ffffff', 96);
            const card = dark ? mix(source, '#18212b', 94) : '#ffffff';
            const hover = mix(source, dark ? '#25313f' : '#ffffff', dark ? 90 : 94);
            const surfaces = [background, card, hover];
            const primary = readable(source, surfaces, target, 4.5);
            palettes[scheme] = {
                primary, background, card,
                text: readable(mix(source, target, 90), surfaces, target, 7),
                muted: readable(mix(source, target, 55), surfaces, target, 4.5),
                decorative: mix(source, dark ? '#36414b' : '#e4e9ee', 92),
                line: readable(mix(source, background, 65), surfaces, target, 3),
                focus: primary, raised: card, hover, soft: hover,
                'primary-hover': mix(primary, target, 12), 'on-primary': dark ? '#000000' : '#ffffff', scheme,
            };
        }
        return palettes;
    }
    globalThis.ClientPalette = Object.freeze({generate, contrast});
})();
