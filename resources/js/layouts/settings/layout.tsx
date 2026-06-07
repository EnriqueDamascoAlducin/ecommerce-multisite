import type { PropsWithChildren } from 'react';
import Heading from '@/components/heading';

export default function SettingsLayout({ children }: PropsWithChildren) {
    return (
        <div className="space-y-6">
            <Heading
                title="Settings"
                description="Manage your profile and account settings"
            />

            <div className="max-w-2xl">
                {children}
            </div>
        </div>
    );
}
