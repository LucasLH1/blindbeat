<div
    wire:poll.30s="pruneFinished"
    class="fixed bottom-0 right-0 z-50 flex flex-col gap-3 p-4"
    style="z-index: 50"
>
    @foreach ($notifications as $notif)
        <div
            wire:key="group-toast-{{ $notif['room_code'] }}"
            x-data="{ show: false }"
            x-init="$nextTick(() => show = true)"
            x-show="show"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-x-8"
            x-transition:enter-end="opacity-100 translate-x-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-x-0"
            x-transition:leave-end="opacity-0 translate-x-8"
            class="bg-white rounded-2xl shadow-lg border-l-4 border-primary px-4 py-3 flex items-start gap-3"
            style="min-width: 300px"
        >
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-ink">
                    🎵 {{ $notif['launched_by'] }} a lancé une partie !
                </p>
                @if ($notif['group_name'])
                    <p class="text-xs text-muted mt-0.5 truncate">{{ $notif['group_name'] }}</p>
                @endif
                <div class="mt-2 flex items-center gap-2">
                    <button
                        wire:click="joinRoom('{{ $notif['room_code'] }}')"
                        class="inline-flex items-center rounded-full bg-primary px-3 py-1 text-xs font-semibold text-white hover:bg-primary-dark transition-colors"
                    >Rejoindre →</button>
                </div>
            </div>
            <button
                wire:click="dismiss('{{ $notif['room_code'] }}')"
                class="text-muted hover:text-ink transition-colors leading-none text-lg"
                title="Fermer"
            >&times;</button>
        </div>
    @endforeach
</div>

@script
<script>
    const groupIds = @json($groupIds);

    groupIds.forEach((id) => {
        window.Echo.private(`group.${id}`)
            .listen('GroupGameStarted', (data) => {
                $wire.addNotification({ ...data, group_id: id });
            });
    });
</script>
@endscript
