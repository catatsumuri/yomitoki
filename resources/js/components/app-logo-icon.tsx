import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="0 0 140 102"
            xmlns="http://www.w3.org/2000/svg"
        >
            <path
                fill="currentColor"
                d="m136.5 2.3h-4.5c-2.6 0-5 0.8-6.5 2.4l-33.2 33.8c-1.1 1.1-2.6 1.4-3.9 0.3l-1.5-1.4-32-32.6c-1.3-1.3-3.2-2.5-5.6-2.5h-6.7c-1.8 0-2.8 2.2-1.4 3.6l39.2 40.9c2.7 2.8 4.4 5.7 4.4 10.5v23.7c0 3.5-2.5 6.7-6.9 6.7h-74.1c-1.5 0-1.9 2.2-0.9 3.3l6.2 6.5c1 1.1 2.3 2.2 4.7 2.3h68.1c7.6 0 13.1-5.9 13.2-12.6v-33.2c0.1-3.5 1.2-7.1 4-10.1l38.3-39.2c0.9-0.9 0.4-2.4-0.9-2.4z"
            />
            <path
                fill="currentColor"
                opacity="0.6"
                d="m42.2 55.1c1.7 0 0.8-4.1-3-6.1l-21.9-10.6c-0.7-0.3-1.1-0.4-1.1 0.1v15c0 0.8 0.6 1.6 1.5 1.6h24.5z"
            />
            <path
                fill="currentColor"
                opacity="0.6"
                d="m63.7 66.2c-0.7-0.7-1.5-1.2-2.5-1.2h-57.6c-0.5 0-0.9 0.3-0.1 1.1l8.7 9.4c0.8 0.9 1.8 1.8 3.7 1.8h55c1.5 0 2-1.5 0.7-2.7l-7.9-8.4z"
            />
        </svg>
    );
}
