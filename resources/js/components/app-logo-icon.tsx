import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 40 42" xmlns="http://www.w3.org/2000/svg">
            <path
                d="M11 12h4v18h-4V12Zm7.5 18 7-18h3.8l7 18H32l-1.4-4h-6.5l-1.4 4h-4.2Zm6.7-7.2h4.3l-2.1-6-2.2 6Z"
                fill="currentColor"
            />
        </svg>
    );
}
