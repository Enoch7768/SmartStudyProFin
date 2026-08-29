<?php
 return [
  'name' => 'ContactDetails',
  'label' => 'Contact_Details',
  'info' => 'Contact_Details',
  'type' => 'singleton',
  'fields' => [
    0 => [
      'name' => 'phone',
      'type' => 'text',
      'label' => 'Phone Number',
      'info' => 'Please input the phone number which is to be used in the website',
      'group' => 'Contact_Details',
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
      'name' => 'email',
      'type' => 'text',
      'label' => 'Email',
      'info' => 'Please input the email which is to be used in the website',
      'group' => 'Contact_Details',
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
      'name' => 'address',
      'type' => 'wysiwyg',
      'label' => 'Address',
      'info' => 'Please input the address which is to be used in the website',
      'group' => 'Contact_Details',
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
  'group' => 'Contact_Details',
  'meta' => NULL,
  '_created' => 1769538638,
  '_modified' => 1769538689,
  'color' => NULL,
  'revisions' => false,
];