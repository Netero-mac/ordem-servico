@props(['size' => 'size-8'])
{{-- Logo completo: quadrado bordô com o ícone em marfim. Mesmas cores nos modos claro e escuro. --}}
<span {{ $attributes->class([$size, 'inline-flex shrink-0 items-center justify-center rounded-lg bg-brand-800 text-ivory shadow-sm ring-1 ring-black/10']) }}>
    <x-app-logo-icon class="size-[62%]" />
</span>
