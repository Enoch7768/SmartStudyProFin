<?php
 return [
  'name' => 'AboutPage',
  'label' => 'About_Page',
  'info' => 'About_Page',
  'type' => 'singleton',
  'fields' => [
    0 => [
      'name' => 'Main-Title',
      'type' => 'text',
      'label' => 'Main Title',
      'info' => 'This is the main title for the about page.',
      'group' => 'About_Page',
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
      'name' => 'Story-Text',
      'type' => 'wysiwyg',
      'label' => 'About SmartStudyPro',
      'info' => 'Describe SmartStudyPro in as how many words you like',
      'group' => 'About_Page',
      'i18n' => false,
      'required' => false,
      'multiple' => false,
      'meta' => [
      ],
      'opts' => [
      ],
    ],
    2 => [
      'name' => 'About-Image',
      'type' => 'asset',
      'label' => 'About-Image',
      'info' => 'Please upload the image for the about page',
      'group' => 'About_Page',
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
  'group' => 'About_Page',
  'meta' => NULL,
  '_created' => 1769538465,
  '_modified' => 1769538465,
  'color' => NULL,
  'revisions' => false,
];