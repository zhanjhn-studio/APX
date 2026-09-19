<?php
/** Partial: 头像。 $user 含 username/nickname/avatar， $size 可选 sm|md|lg。 */
$avUser = $user ?? [];
$avSize = $size ?? '';
$avCls = 'apx-avatar' . ($avSize ? ' apx-avatar--' . $avSize : '');
$avInitial = !empty($avUser['nickname'])
    ? mb_substr($avUser['nickname'], 0, 1)
    : (!empty($avUser['username']) ? mb_substr($avUser['username'], 0, 1) : '?');
if (!empty($avUser['avatar'])):
    $avSrc = e(asset('uploads/' . ltrim($avUser['avatar'], '/'))); ?>
    <img class="<?= e($avCls) ?>" src="<?= $avSrc ?>" alt="" onerror="this.outerHTML='<span class=&quot;<?= e($avCls) ?>&quot;><?= e($avInitial) ?></span>'">
<?php else: ?>
    <span class="<?= e($avCls) ?>"><?= e($avInitial) ?></span>
<?php endif; ?>
