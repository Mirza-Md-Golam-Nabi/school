<x-filament-panels::page>
    <div class="mb-4 text-sm text-gray-600 dark:text-gray-400">
        নিচে প্রতিটি Letter Grade-এর জন্য Marks Interval (Min - Max) এবং Grade Point দিন। মার্ক অনুযায়ী গ্রেড বের করার সময় Min Mark থেকে সবচেয়ে বড় ম্যাচটি ব্যবহার করা হয়, তাই দুইটি রেঞ্জের মাঝে কোনো ফাঁক থাকলেও (যেমন ৭৯.৫) সঠিক গ্রেড পাওয়া যাবে।
    </div>

    {{ $this->form }}
</x-filament-panels::page>
