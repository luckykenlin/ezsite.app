<?php

declare(strict_types=1);

/*
 * 中文校验消息 —— 只覆盖营销站注册向导实际用到的规则。
 *
 * 这是一层「覆盖」，不是全量翻译：Laravel 找不到某条键时会回落到 fallback_locale
 * （en）自带的消息，所以这里只需要放向导会触发的那几条。租户后台仍然跑在 en 上，
 * 不受影响。
 *
 * attributes 把驼峰字段名换成人话 —— 没有它，:attribute 会渲染成 “business name”。
 *
 * @see \App\Livewire\Central\ApplyTemplate::next()
 */

return [
    'required' => '请填写:attribute。',
    'string' => ':attribute 必须是文本。',
    'max' => [
        'string' => ':attribute 不能超过 :max 个字。',
    ],
    'min' => [
        'string' => ':attribute 至少要 :min 位。',
    ],
    'email' => '请填写一个有效的邮箱地址。',

    'attributes' => [
        'businessName' => '店名',
        'subdomain' => '网址',
        'tagline' => '一句话介绍',
        'city' => '城市',
        'phone' => '电话',
        'email' => '邮箱',
        'password' => '密码',
    ],
];
