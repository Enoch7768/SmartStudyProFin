<?php
 return [
  'name' => 'HomePage',
  'label' => 'Home_Page',
  'info' => 'Home_Page',
  'type' => 'singleton',
  'fields' => [
    0 => [
      'name' => 'Hero-Title',
      'type' => 'text',
      'label' => 'Hero Title',
      'info' => 'Please input the title for your hero',
      'group' => 'Home_Page',
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
      'name' => 'Hero-Subtitle',
      'type' => 'text',
      'label' => 'Hero Subtitle',
      'info' => 'Please input the subtitle for the hero',
      'group' => 'Home_Page',
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
      'name' => 'Hero-Image',
      'type' => 'asset',
      'label' => 'Hero Image',
      'info' => 'This is the bacground image for hero',
      'group' => 'Home_Page',
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
  'group' => 'Home_Page',
  'meta' => NULL,
  '_created' => 1769538253,
  '_modified' => 1769538253,
  'color' => NULL,
  'revisions' => false,
];