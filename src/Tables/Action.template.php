<?php
    if ($action == 'add' || $action == 'addMultiple' || $action == 'accept' || $action == 'approve') {
        $hoverClass = 'hover:text-green-500 hover:border-green-500';
    } elseif ($action == 'delete' || $action == 'reject' || $action == 'decline' || $action == 'cancel') {
        $hoverClass = 'hover:text-red-700 hover:border-red-700';
    } else {
        $hoverClass = 'hover:text-blue-500 hover:border-blue-500';
    }
?>

<?php
    $radiusClass = !empty($groupClass) ? $groupClass : 'rounded-md';
    $paddingClass = !empty($compact) ? 'px-2 py-1' : 'px-3 py-2';
    $shadowClass = !empty($compact) ? 'relative hover:z-10' : 'shadow-sm';
?>

<a <?= $attributes; ?> <?= !$modal ? '@click="modalOpen = false"' : '' ?> title="<?= !$displayLabel ? $label : ''; ?>"
    class="<?= $class; ?> inline-flex items-center align-middle <?= $radiusClass; ?> bg-white <?= $paddingClass; ?> text-sm font-semibold <?= $shadowClass; ?> border border-gray-400 hover:bg-gray-100 <?= $hoverClass; ?> <?= $displayLabel ? 'text-gray-600 lg:text-gray-500' : 'text-gray-600'; ?>">

    <?php $svgClass = (!empty($compact) ? 'w-4 h-4 ' : 'w-6 h-6 sm:h-5 sm:w-5 ').($displayLabel ? 'lg:-ml-0.5 lg:mr-1.5 ' : '').($iconClass ?? ''); ?>

    <?= icon($iconLibrary ?? 'solid', $icon ?? $action, $svgClass) ?>
    
    <?php if ($displayLabel) { ?>
    <span class="hidden lg:block text-gray-800 whitespace-nowrap">
        <?= $label; ?>
    </span>
    <?php } ?>
</a>
