import { Facebook, Instagram, Linkedin, Twitter, Youtube } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';

export type CintilloSocial = { platform: string; url: string };

export type CintilloBlock = {
    type: 'text' | 'social' | 'image';
    text?: string | null;
    social?: CintilloSocial[];
    url?: string | null;
    alt?: string | null;
    link?: string | null;
};

export type CintilloData = {
    enabled: boolean;
    show_on_mobile: boolean;
    blocks: CintilloBlock[];
    text_color: string;
    background_color: string;
};

const SOCIAL_ICONS: Record<string, LucideIcon> = {
    facebook: Facebook,
    instagram: Instagram,
    twitter: Twitter,
    youtube: Youtube,
    linkedin: Linkedin,
};

function isRenderable(block: CintilloBlock): boolean {
    if (block.type === 'social') {
        return (block.social ?? []).some((social) => SOCIAL_ICONS[social.platform]);
    }

    if (block.type === 'image') {
        return Boolean(block.url);
    }

    return Boolean(block.text && block.text.trim() !== '');
}

/**
 * Franja superior configurable del storefront. Renderiza hasta 3 bloques (texto
 * o redes) distribuidos en la fila. Con `preview` los iconos no son enlaces.
 */
export function CintilloBar({
    cintillo,
    preview = false,
}: {
    cintillo: CintilloData;
    preview?: boolean;
}) {
    if (!cintillo.enabled) {
        return null;
    }

    const blocks = cintillo.blocks.filter(isRenderable);

    if (blocks.length === 0) {
        return null;
    }

    return (
        <div
            style={{
                color: cintillo.text_color,
                backgroundColor: cintillo.background_color,
            }}
            className={cn('w-full', !cintillo.show_on_mobile && 'hidden md:block')}
        >
            <div
                className={cn(
                    'mx-auto flex w-full max-w-6xl items-center gap-4 px-4 py-2 text-sm',
                    blocks.length === 1 ? 'justify-center' : 'justify-between',
                )}
            >
                {blocks.map((block, index) => (
                    <BlockContent key={index} block={block} preview={preview} />
                ))}
            </div>
        </div>
    );
}

function BlockContent({
    block,
    preview,
}: {
    block: CintilloBlock;
    preview: boolean;
}) {
    if (block.type === 'text') {
        return <span>{block.text}</span>;
    }

    if (block.type === 'image') {
        const image = (
            <img
                src={block.url ?? ''}
                alt={block.alt ?? ''}
                className="h-6 w-auto object-contain"
            />
        );

        if (block.link && !preview) {
            return (
                <a href={block.link} target="_blank" rel="noopener noreferrer">
                    {image}
                </a>
            );
        }

        return image;
    }

    return (
        <div className="flex items-center gap-3">
            {(block.social ?? []).map((social) => {
                const Icon = SOCIAL_ICONS[social.platform];

                if (!Icon) {
                    return null;
                }

                if (preview) {
                    return <Icon key={social.platform} className="size-4" />;
                }

                return (
                    <a
                        key={social.platform}
                        href={social.url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="transition-opacity hover:opacity-70"
                    >
                        <Icon className="size-4" />
                        <span className="sr-only">{social.platform}</span>
                    </a>
                );
            })}
        </div>
    );
}
