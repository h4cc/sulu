<?php
/*
 * This file is part of the Sulu CMS.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ContentBundle\Tests\Functional\Search;

class DeleteStructureTest extends BaseTestCase
{
    public function testDeleteStructure()
    {
        $searchManager = $this->getSearchManager();
        $mapper = $this->getContainer()->get('sulu.content.mapper');

        $structure = $this->indexStructure('About Us', '/about-us');
        $res = $searchManager->createSearch('About')->locale('de')->index('page')->execute();
        $this->assertCount(1, $res);

        $mapper->delete($structure->getUuid(), 'sulu_io');
        $res = $searchManager->createSearch('About')->locale('de')->index('page')->execute();

        $this->assertCount(0, $res);
    }
}
