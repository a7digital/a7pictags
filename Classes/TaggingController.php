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

use Doctrine\DBAL\Connection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileRepository;
use TYPO3\CMS\Core\Resource\Index\MetaDataRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class TaggingController
{
    /**
     * @var DataHandler
     */
    private $dataHandler;

    /**
     * @var ConnectionPool
     */
    private $connectionPool;

    /** @var string[] $problems */
    private $problems = [];

    public function __construct()
    {
        $this->dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $this->connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
    }

    public function tagSingleFile(ServerRequestInterface $request): ResponseInterface
    {
        $arguments = $request->getParsedBody();
        $tags = json_decode($arguments['tags'], true);
        $fileUid = $arguments['fileUid'];
        /** @var FileRepository $fileRepository */
        $fileRepository = GeneralUtility::makeInstance(FileRepository::class);
        /** @var File $file */
        $file = $fileRepository->findByUid($fileUid);
        /** @var MetaDataRepository $metaDataRepository */
        $metaDataRepository = GeneralUtility::makeInstance(MetaDataRepository::class);
        $metaData = $metaDataRepository->findByFile($file);
        $categoryUids = $this->makeCategories($tags);
        $this->setCategoryRelations($categoryUids, $metaData['uid']);
        return new JsonResponse([
            'tags' => $tags,
            'problems' => $this->problems,
        ]);
    }

    /**
     * @param int[] $categoryUids
     * @param int $metaDataUid
     */
    private function setCategoryRelations(array $categoryUids, int $metaDataUid)
    {
        $this->dataHandler->start([], []);
        if (!$this->dataHandler->checkModifyAccessList('sys_category_record_mm')) {
            $this->problems[] = [
                'type' => 'sys_category_mm_permission_denied',
            ];
            return;
        }
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_category_record_mm');
        $queryBuilder->from('sys_category_record_mm');
        $queryBuilder->addSelect('uid_local');
        $queryBuilder->andWhere('uid_foreign = :uid_foreign', 'fieldname = "categories"', 'uid_local in (:uids_local)', 'tablenames = "sys_file_metadata"');
        $queryBuilder->setParameter('uid_foreign', $metaDataUid);
        $queryBuilder->setParameter('uids_local', $categoryUids, Connection::PARAM_STR_ARRAY);
        $foundRecords = $queryBuilder->execute()->fetchAll();
        $foundUids = [];
        foreach ($foundRecords as $foundRecord) {
            $foundUids[] = $foundRecord['uid_local'];
        }
        $missingCategoryUids = array_diff($categoryUids, $foundUids);
        if ($missingCategoryUids) {
            foreach ($categoryUids as $categoryUid) {
                $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_category_record_mm');
                $queryBuilder->insert('sys_category_record_mm');
                $queryBuilder->values([
                    'uid_local' => $categoryUid,
                    'uid_foreign' => $metaDataUid,
                    'tablenames' => 'sys_file_metadata',
                    'fieldname' => 'categories',
                ]);
                $queryBuilder->execute();
                $this->dataHandler->updateRefIndex('sys_category', $categoryUid);
            }
            $this->dataHandler->updateRefIndex('sys_file_metadata', $metaDataUid);
        }
    }

    /**
     * @param string[] $titles
     * @return array
     */
    private function readCategories(array $titles): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_category');
        $queryBuilder->from('sys_category');
        $queryBuilder->addSelect('uid', 'title');
        $queryBuilder->andWhere('title in (:titles)');
        $queryBuilder->setParameter('titles', $titles, Connection::PARAM_STR_ARRAY);
        return $queryBuilder->execute()->fetchAll();
    }

    /**
     * @param string[] $tags
     * @return int[]
     */
    private function makeCategories(array $tags): array
    {
        $categories = $this->readCategories($tags);
        $uids = [];
        foreach ($categories as $category) {
            $uids[] = $category['uid'];
        }
        $foundTags = [];
        foreach ($categories as $category) {
            $foundTags[] = $category['title'];
        }
        $newTags = array_diff($tags, $foundTags);
        if ($newTags && !$this->dataHandler->checkRecordInsertAccess('sys_category', 0)) {
            $this->problems[] = [
                'type' => 'sys_category_insert_permission_denied',
                'titles' => $newTags,
            ];
        } else {
            foreach ($newTags as $newTag) {
                $uids[] = $this->dataHandler->insertDB('sys_category', 'NEW' . random_int(100000, 999999), [
                    'title' => $newTag,
                ]);
            }
        }
        return $uids;
    }
}
