{{-- 
    Responsive Table Component
    Usage: Wrap your table with this component, and duplicate content as mobile cards
    
    Example:
    <x-responsive-table>
        <x-slot name="desktop">
            <table class="table">...</table>
        </x-slot>
        <x-slot name="mobile">
            <div class="space-y-4">
                @foreach($items as $item)
                    <div class="card">...</div>
                @endforeach
            </div>
        </x-slot>
    </x-responsive-table>
--}}

<div>
    {{-- Desktop Table View --}}
    <div class="hidden md:block overflow-x-auto">
        {{ $desktop }}
    </div>

    {{-- Mobile Card View --}}
    <div class="md:hidden">
        {{ $mobile }}
    </div>
</div>
