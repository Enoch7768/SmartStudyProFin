<?php
 return [
  'name' => 'Products',
  'label' => 'Products',
  'info' => 'Products',
  'type' => 'collection',
  'fields' => [
    0 => [
      'name' => 'Title',
      'type' => 'text',
      'label' => 'Product Name',
      'info' => 'Please input the name of your desired product.',
      'group' => 'Products',
      'i18n' => false,
      'required' => false,
      'multiple' => false,
      'meta' => [
      ],
      'opts' => [
      ],
    ],
    1 => [
      'name' => 'Description',
      'type' => 'wysiwyg',
      'label' => 'Product Description',
      'info' => 'Please input the description of your desired product.',
      'group' => 'Products',
      'i18n' => false,
      'required' => false,
      'multiple' => false,
      'meta' => [
      ],
      'opts' => [
      ],
    ],
    2 => [
      'name' => 'Price',
      'type' => 'number',
      'label' => 'Product Price',
      'info' => 'Please input the price of your desired product',
      'group' => 'Products',
      'i18n' => false,
      'required' => false,
      'multiple' => false,
      'meta' => [
      ],
      'opts' => [
      ],
    ],
    3 => [
      'name' => 'Category',
      'type' => 'text',
      'label' => 'Product Category',
      'info' => 'Please input the category of your desired product.',
      'group' => 'Products',
      'i18n' => false,
      'required' => false,
      'multiple' => false,
      'meta' => [
      ],
      'opts' => [
      ],
    ],
    4 => [
      'name' => 'Image',
      'type' => 'asset',
      'label' => 'Product Image',
      'info' => 'Please upload or choose the desired image for your product.',
      'group' => 'Products',
      'i18n' => false,
      'required' => false,
      'multiple' => false,
      'meta' => [
      ],
      'opts' => [
      ],
    ],
    5 => [
      'name' => 'ProductType',
      'type' => 'select',
      'label' => 'Product Type',
      'info' => 'Please select the type of your desired product.',
      'group' => 'Products',
      'i18n' => false,
      'required' => false,
      'multiple' => false,
      'meta' => [
      ],
      'opts' => [
        'multiple' => false,
        'options' => [
          0 => 'Online',
          1 => 'Physical',
        ],
      ],
    ],
    6 => [
      'name' => 'ProductFile',
      'type' => 'asset',
      'label' => 'Product File',
      'info' => 'Please upload the file for your online product.',
      'group' => 'Products',
      'i18n' => false,
      'required' => false,
      'multiple' => false,
      'meta' => [
      ],
      'opts' => [
      ],
      'condition' => '{
  "show_if": "ProductType == \'Online\'",
   "hide_if":"ProductType == \'Physical\'"
}
',
    ],
  ],
  'preview' => [
  ],
  'group' => 'Products',
  'meta' => NULL,
  '_created' => 1769602278,
  '_modified' => 1769604026,
  'color' => NULL,
  'revisions' => false,
];