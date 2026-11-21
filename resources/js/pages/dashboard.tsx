import { Head } from '@inertiajs/react';
import { useState } from 'react';
import PendingInvitationsModal from '@/components/pending-invitations-modal';
import { Button } from '@/components/ui/button';
import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import { dashboard } from '@/routes';
import { index as childProfiles } from '@/routes/filament/parent/resources/child-profiles';
import type { DashboardInvitation } from '@/types';

type Props = {
    canManageHousehold: boolean;
    pendingInvitations?: DashboardInvitation[];
};

export default function Dashboard({
    canManageHousehold,
    pendingInvitations = [],
}: Props) {
    const [showInvitations, setShowInvitations] = useState(
        pendingInvitations.length > 0,
    );

    return (
        <>
            <Head title="Dashboard" />
            <PendingInvitationsModal
                invitations={pendingInvitations}
                open={pendingInvitations.length > 0 && showInvitations}
                onOpenChange={setShowInvitations}
            />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto px-6 pb-8 md:px-8 md:pb-10">
                {canManageHousehold && (
                    <Button asChild className="self-start">
                        <a
                            href={childProfiles.url()}
                            data-test="open-parent-panel"
                        >
                            מרחב הורים
                        </a>
                    </Button>
                )}
                <div className="grid auto-rows-min gap-6 md:grid-cols-3">
                    <div className="relative aspect-video overflow-hidden rounded-2xl bg-card shadow-soft">
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-primary/15" />
                    </div>
                    <div className="relative aspect-video overflow-hidden rounded-2xl bg-card shadow-soft">
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-primary/15" />
                    </div>
                    <div className="relative aspect-video overflow-hidden rounded-2xl bg-card shadow-soft">
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-primary/15" />
                    </div>
                </div>
                <div className="relative min-h-[100vh] flex-1 overflow-hidden rounded-2xl bg-card shadow-soft md:min-h-min">
                    <PlaceholderPattern className="absolute inset-0 size-full stroke-primary/15" />
                </div>
            </div>
        </>
    );
}

Dashboard.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: props.currentTeam ? dashboard(props.currentTeam.slug) : '/',
        },
    ],
});
