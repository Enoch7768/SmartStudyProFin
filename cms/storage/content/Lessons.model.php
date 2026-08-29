<?php
 return [
  'name' => 'Lessons',
  'label' => 'Lessons',
  'info' => 'Lessons',
  'type' => 'collection',
  'fields' => [
    0 => [
      'name' => 'Title ',
      'type' => 'text',
      'label' => 'Title ',
      'info' => 'Title ',
      'group' => 'Title ',
      'i18n' => false,
      'required' => false,
      'multiple' => false,
      'meta' => [
      ],
      'opts' => [
        'multiline' => false,
        'showCount' => true,
        'readonly' => false,
        'placeholder' => NULL,
        'minlength' => NULL,
        'maxlength' => NULL,
        'list' => NULL,
      ],
    ],
    1 => [
      'name' => 'Chapter',
      'type' => 'contentItemLink',
      'label' => 'Chapter',
      'info' => 'Chapter',
      'group' => 'Chapter',
      'i18n' => false,
      'required' => false,
      'multiple' => false,
      'meta' => [
      ],
      'opts' => [
        'link' => 'Chapters',
        'filter' => NULL,
      ],
    ],
    2 => [
      'name' => 'Content',
      'type' => 'text',
      'label' => 'Content',
      'info' => 'Content',
      'group' => 'Content',
      'i18n' => false,
      'required' => false,
      'multiple' => false,
      'meta' => [
      ],
      'opts' => [
        'multiline' => false,
        'showCount' => true,
        'readonly' => false,
        'placeholder' => NULL,
        'minlength' => NULL,
        'maxlength' => NULL,
        'list' => NULL,
      ],
    ],
    3 => [
      'name' => 'Order',
      'type' => 'number',
      'label' => 'Order',
      'info' => 'Order',
      'group' => 'Order',
      'i18n' => false,
      'required' => false,
      'multiple' => false,
      'meta' => [
      ],
      'opts' => [
      ],
    ],
    4 => [
      'name' => 'VIDEO FILE',
      'type' => 'asset',
      'label' => 'VIDEO FILE',
      'info' => 'VIDEO FILE',
      'group' => 'VIDEO FILE',
      'i18n' => false,
      'required' => false,
      'multiple' => false,
      'meta' => [
      ],
      'opts' => [
      ],
    ],
  ],
  'preview' => [
  ],
  'group' => 'Lessons',
  'meta' => NULL,
  '_created' => 1770222270,
  '_modified' => 1770312363,
  'color' => NULL,
  'revisions' => false,
];