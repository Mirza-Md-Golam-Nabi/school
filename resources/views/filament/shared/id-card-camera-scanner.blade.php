{{--
    The camera button under the fee counter's "Scan ID Card" box: opens the
    device camera, reads the card's QR code and hands it to the box exactly as
    a USB scanner would. The scanner script (with jsQR) loads only on tap.

    @var string $statePath  the scan box's Livewire state path
--}}
@php
    $scannerModuleUrl = \Illuminate\Support\Facades\Vite::asset('resources/js/id-card-camera-scanner.js');
@endphp
<div
    x-data="{
        isOpen: false,
        error: null,
        stopScan: null,
        async start() {
            this.isOpen = true;
            this.error = null;
            await this.$nextTick();

            try {
                const { startQrScan } = await import(@js($scannerModuleUrl));

                if (! this.isOpen) {
                    return;
                }

                this.stopScan = await startQrScan(this.$refs.video, (text) => {
                    this.close();
                    this.$wire.set(@js($statePath), text);
                });
            } catch (exception) {
                this.error = {
                    NotAllowedError: 'ক্যামেরার অনুমতি দেওয়া হয়নি। ব্রাউজারের ঠিকানার ঘরের পাশের আইকন থেকে ক্যামেরা Allow করুন।',
                    NotFoundError: 'এই ডিভাইসে কোনো ক্যামেরা পাওয়া যায়নি।',
                    NotReadableError: 'ক্যামেরা অন্য কোনো অ্যাপ ব্যবহার করছে। সেটা বন্ধ করে আবার চেষ্টা করুন।',
                    InsecureContextError: 'ক্যামেরা চালু করতে সাইটটি HTTPS (https://) ঠিকানায় খুলতে হবে।',
                }[exception?.name] ?? 'ক্যামেরা চালু করা যায়নি। পেজ রিলোড করে আবার চেষ্টা করুন।';
            }
        },
        close() {
            this.stopScan?.();
            this.stopScan = null;
            this.isOpen = false;
        },
    }"
    x-on:keydown.escape.window="isOpen && close()"
    x-on:livewire:navigating.window="close()"
>
    <button
        type="button"
        x-on:click="start()"
        class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-xs font-semibold text-primary-600 ring-1 ring-primary-600/30 hover:bg-primary-50 sm:text-sm dark:text-primary-400 dark:ring-primary-400/30 dark:hover:bg-white/5"
    >
        <x-filament::icon icon="heroicon-o-camera" class="h-4 w-4" />
        ক্যামেরা দিয়ে স্ক্যান
    </button>

    <template x-teleport="body">
        <div
            x-show="isOpen"
            x-cloak
            x-transition.opacity
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-3"
            role="dialog"
            aria-modal="true"
            aria-label="আইডি কার্ড স্ক্যান"
        >
            <div class="w-full max-w-md overflow-hidden rounded-xl bg-white shadow-xl dark:bg-gray-900" x-on:click.outside="close()">
                <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-white/10">
                    <span class="text-xs font-semibold text-gray-950 sm:text-sm dark:text-white">আইডি কার্ডের QR ক্যামেরার সামনে ধরুন</span>
                    <button type="button" x-on:click="close()" class="rounded-lg p-1 text-gray-500 hover:bg-gray-100 dark:hover:bg-white/5" aria-label="বন্ধ করুন">
                        <x-filament::icon icon="heroicon-o-x-mark" class="h-5 w-5" />
                    </button>
                </div>

                <div class="relative aspect-square w-full bg-black" x-show="! error">
                    <video x-ref="video" class="h-full w-full object-cover" playsinline muted></video>
                    <div class="pointer-events-none absolute inset-[18%] rounded-xl border-2 border-white/80 shadow-[0_0_0_9999px_rgb(0_0_0/0.35)]"></div>
                </div>

                <p x-show="error" x-text="error" class="px-4 py-6 text-xs text-danger-600 sm:text-sm dark:text-danger-400"></p>

                <div class="flex justify-end px-4 py-3">
                    <button type="button" x-on:click="close()" class="rounded-lg px-3 py-2 text-xs font-semibold text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50 sm:text-sm dark:text-gray-200 dark:ring-white/20 dark:hover:bg-white/5">
                        বাতিল
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>
