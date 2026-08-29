<?php
 return [
  'name' => 'Chapters',
  'label' => 'Chapters',
  'info' => 'Course Chapters',
  'type' => 'collection',
  'fields' => [
    0 => [
      'name' => 'Title',
      'type' => 'text',
      'label' => 'Course Title',
      'info' => 'Please type your desired course title.',
      'group' => 'Title',
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
      'name' => 'Course Name',
      'type' => 'text',
      'label' => 'Course Name',
      'info' => 'Please type the desired name for your course.',
      'group' => 'Course Name',
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
    2 => [
      'name' => 'Order',
      'type' => 'number',
      'label' => 'Chapter ID',
      'info' => 'Please input id for chapter ',
      'group' => 'Order',
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
  'group' => 'Chapters',
  'meta' => NULL,
  '_created' => 1770222009,
  '_modified' => 1770224079,
  'color' => NULL,
  'revisions' => false,
];