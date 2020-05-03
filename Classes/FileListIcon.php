<?php
declare(strict_types=1);

namespace A7digital\A7pictags;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2020, a7digital GmbH
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Index\MetaDataRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class FileListIcon implements \TYPO3\CMS\Filelist\FileListEditIconHookInterface
{
    /**
     * @var IconFactory
     */
    protected $iconFactory;
    /** @var MetaDataRepository */
    protected $metadataRepository;

    public function __construct()
    {
        $this->iconFactory = GeneralUtility::makeInstance(IconFactory::class);
        $this->metadataRepository = GeneralUtility::makeInstance(MetaDataRepository::class);
    }

    /**
     * @inheritDoc
     */
    public function manipulateEditIcons(&$cells, &$parentObject)
    {
        $pageRenderer = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Page\PageRenderer::class);
        $pageRenderer->loadRequireJsModule('TYPO3/CMS/A7pictags/A7pictagsFileList');
        $pageRenderer->addCssFile('EXT:a7pictags/Resources/Public/Style/a7pictags-filelist.css', 'stylesheet', 'all', '', false);

        $taggingInfo = $this->getTaggingInfo($cells);
        if ($taggingInfo) {
            $cells['a7pictags'] =
                  '<a href="#"
                      class="btn btn-default a7pictags a7pictags-target"
                      data-info="' . htmlspecialchars(json_encode($taggingInfo)) . '"
                      title="' . $this->getLanguageService()->sL('LLL:EXT:a7pictags/Resources/Private/Language/locallang.xlf:filelist.icon.title') . '"
                      >'
                . $this->iconFactory->getIcon('auto-tag', Icon::SIZE_SMALL)->render()
                . $this->iconFactory->getIcon('spinner-circle-dark', Icon::SIZE_SMALL)->render()
                . '</a>';
        }
    }

    /**
     * Returns an instance of LanguageService
     *
     * @return \TYPO3\CMS\Core\Localization\LanguageService
     */
    protected function getLanguageService()
    {
        return $GLOBALS['LANG'];
    }

    private function getTaggingInfo(array $cells)
    {
        $info = [];
        $fileOrFolder = $cells['__fileOrFolderObject'];
        if ($fileOrFolder instanceof File) {
            $file = $fileOrFolder;
            if (!preg_match('/\.(jpe?g|webp|gif|a?png|svg|bmp|ico)/i', $file->getName())) {
                return [];
            }
            $info['url'] = '/' . $file->getPublicUrl();
            $info['uid'] = $file->getUid();
        }
        return $info;
    }
}
