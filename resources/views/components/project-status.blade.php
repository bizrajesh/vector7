@props(['status'])
@php($cls = ['draft' => 'badge-gray', 'init' => 'badge-blue', 'go_no_go' => 'badge-amber', 'in_progress' => 'badge-purple', 'ready_to_launch' => 'badge-teal', 'launched' => 'badge-navy', 'closed' => 'badge-gray'][$status] ?? 'badge-gray')
<span {{ $attributes->merge(['class' => $cls]) }}>{{ \App\Models\Project::STATUSES[$status] ?? $status }}</span>
