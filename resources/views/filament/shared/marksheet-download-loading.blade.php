{{-- Shown inside the marksheet download modal while the PDF is being prepared, so the admin knows the system is working. --}}
<div
    wire:loading.flex
    wire:target="callMountedAction"
    style="display: none;"
    class="items-center gap-3 rounded-lg border border-primary-200 bg-primary-50 p-3 text-xs text-primary-700 sm:text-sm dark:border-primary-800 dark:bg-primary-950 dark:text-primary-300"
>
    <x-filament::loading-indicator class="h-5 w-5 shrink-0" />
    <span>Marksheet PDF প্রস্তুত হচ্ছে, অনুগ্রহ করে অপেক্ষা করুন… download নিজে থেকেই শুরু হবে।</span>
</div>
