/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './app/**/*.php',
        './packages/inovcom/**/resources/views/**/*.blade.php',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', '"Segoe UI"', '"Helvetica Neue"', 'Arial', 'sans-serif'],
            },
            fontSize: {
                '2xs': ['10px', '14px'],
                '11': ['11px', '16px'],
                '13': ['13px', '18px'],
            },
            spacing: {
                '13': '13px',
            },
            minHeight: {
                '13': '52px',
            },
            width: {
                '60': '240px',
            },
            height: {
                '8': '32px',
                '7': '28px',
            },
            zIndex: {
                '100': '100',
                '500': '500',
            },
            borderRadius: {
                'none': '0px',
            },
            colors: {
                sidebar: {
                    bg: '#1e293b',
                    border: '#334155',
                    link: '#94a3b8',
                    'link-hover': '#f1f5f9',
                    'link-active': '#f8fafc',
                    'link-active-bg': '#334155',
                },
            },
            boxShadow: {
                'topbar': '0 1px 3px rgba(0,0,0,0.06)',
                'card': '0 1px 3px rgba(0,0,0,0.04)',
                'modal': '0 8px 32px rgba(0,0,0,0.18)',
            },
        },
    },
    plugins: [],
};
