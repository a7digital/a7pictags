<?php

$iconRegistry = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Imaging\IconRegistry::class);
$iconRegistry->registerIcon(
    'auto-tag',
    \TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class,
    ['source' => 'EXT:a7pictags/Resources/Public/Image/tag.svg']
);

if (TYPO3_MODE=="BE" )   {
    $pageRenderer = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Page\PageRenderer::class);

    $pageRenderer->addInlineSetting('a7neuralnet.nets', 'a7pictags', [
        'url' => 'https://m.softwar3.com/a7pictags-88-4194492.onnx',
        'name' => 'onnx',
        'type' => 'convolutional',
        'input' => [
            'width' => 150,
            'height' => 150,
            'channels' => [0, 1, 2],
            'fillMode' => 'fit-black',
            'valueRange' => [-1, +1],
        ],
        'output' => [
            'type' => 'multi-class',
            'labels' => 'https://m.softwar3.com/used_tags.set.txt',
        ],
    ]);
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['fileList']['editIconsHook']['a7pictags'] = \A7digital\A7pictags\FileListIcon::class;
}
