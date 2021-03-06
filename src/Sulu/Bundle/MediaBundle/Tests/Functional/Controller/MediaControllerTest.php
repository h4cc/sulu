<?php
/*
 * This file is part of the Sulu CMS.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\MediaBundle\Tests\Functional\Controller;

use DateTime;
use Doctrine\ORM\EntityManager;
use Sulu\Bundle\MediaBundle\Entity\Collection;
use Sulu\Bundle\MediaBundle\Entity\CollectionMeta;
use Sulu\Bundle\MediaBundle\Entity\CollectionType;
use Sulu\Bundle\MediaBundle\Entity\File;
use Sulu\Bundle\MediaBundle\Entity\FileVersion;
use Sulu\Bundle\MediaBundle\Entity\FileVersionMeta;
use Sulu\Bundle\MediaBundle\Entity\Media;
use Sulu\Bundle\MediaBundle\Entity\MediaType;
use Sulu\Bundle\TagBundle\Entity\Tag;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class MediaControllerTest extends SuluTestCase
{
    /**
     * @var EntityManager
     */
    private $em;

    /**
     * @var CollectionType
     */
    private $collectionType;

    /**
     * @var Collection
     */
    private $collection;

    /**
     * @var CollectionMeta
     */
    private $collectionMeta;

    /**
     * @var MediaType
     */
    private $documentType;

    /**
     * @var MediaType
     */
    private $imageType;

    /**
     * @var MediaType
     */
    private $videoType;

    /**
     * @var string
     */
    protected $mediaDefaultTitle = 'photo';

    /**
     * @var string
     */
    protected $mediaDefaultDescription = 'description';

    protected function setUp()
    {
        parent::setUp();
        $this->purgeDatabase();
        $this->em = $this->db('ORM')->getOm();
        $this->cleanImage();
        $this->setUpCollection();
        $this->setUpMedia();
    }

    protected function cleanImage()
    {
        if (self::$kernel->getContainer()) { //
            $configPath = self::$kernel->getContainer()->getParameter('sulu_media.media.storage.local.path');
            $this->recursiveRemoveDirectory($configPath);

            $cachePath = self::$kernel->getContainer()->getParameter('sulu_media.format_cache.path');
            $this->recursiveRemoveDirectory($cachePath);
        }
    }

    public function recursiveRemoveDirectory($directory, $counter = 0)
    {
        foreach (glob($directory . '/*') as $file) {
            if (is_dir($file)) {
                $this->recursiveRemoveDirectory($file, $counter + 1);
            } elseif (file_exists($file)) {
                unlink($file);
            }
        }

        if ($counter != 0) {
            rmdir($directory);
        }
    }

    protected function setUpMedia()
    {
        // Create Media Type
        $this->documentType = new MediaType();
        $this->documentType->setName('document');
        $this->documentType->setDescription('This is a document');

        $this->imageType = new MediaType();
        $this->imageType->setName('image');
        $this->imageType->setDescription('This is an image');

        $this->videoType = new MediaType();
        $this->videoType->setName('video');
        $this->videoType->setDescription('This is a video');

        // create some tags
        $tag1 = new Tag();
        $tag1->setName('Tag 1');

        $tag2 = new Tag();
        $tag2->setName('Tag 2');

        $this->em->persist($tag1);
        $this->em->persist($tag2);
        $this->em->persist($this->documentType);
        $this->em->persist($this->imageType);
        $this->em->persist($this->videoType);

        $this->em->flush();
    }

    protected function createMedia($name)
    {
        $media = new Media();
        $media->setType($this->imageType);

        // create file
        $file = new File();
        $file->setVersion(1);
        $file->setMedia($media);

        // create file version
        $fileVersion = new FileVersion();
        $fileVersion->setVersion(1);
        $fileVersion->setName($name . '.jpeg');
        $fileVersion->setMimeType('image/jpg');
        $fileVersion->setFile($file);
        $fileVersion->setSize(1124214);
        $fileVersion->setDownloadCounter(2);
        $fileVersion->setChanged(new \DateTime('1937-04-20'));
        $fileVersion->setCreated(new \DateTime('1937-04-20'));
        $fileVersion->setStorageOptions('{"segment":"1","fileName":"' . $name . '.jpeg"}');
        if (!file_exists(__DIR__ . '/../../uploads/media/1')) {
            mkdir(__DIR__ . '/../../uploads/media/1', 0777, true);
        }
        copy($this->getImagePath(), __DIR__ . '/../../uploads/media/1/' . $name . '.jpeg');

        // create meta
        $fileVersionMeta = new FileVersionMeta();
        $fileVersionMeta->setLocale('en-gb');
        $fileVersionMeta->setTitle($this->mediaDefaultTitle);
        $fileVersionMeta->setDescription($this->mediaDefaultDescription);
        $fileVersionMeta->setFileVersion($fileVersion);

        $fileVersion->addMeta($fileVersionMeta);
        $fileVersion->setDefaultMeta($fileVersionMeta);

        $file->addFileVersion($fileVersion);

        $media->addFile($file);
        $media->setCollection($this->collection);

        $this->em->persist($media);
        $this->em->persist($file);
        $this->em->persist($fileVersionMeta);
        $this->em->persist($fileVersion);

        $this->em->flush();

        return $media;
    }

    protected function setUpCollection()
    {
        $this->collection = new Collection();
        $style = array(
            'type' => 'circle',
            'color' => '#ffcc00',
        );

        $this->collection->setStyle(json_encode($style));

        // Create Collection Type
        $this->collectionType = new CollectionType();
        $this->collectionType->setName('Default Collection Type');
        $this->collectionType->setDescription('Default Collection Type');

        $this->collection->setType($this->collectionType);

        // Collection Meta 1
        $this->collectionMeta = new CollectionMeta();
        $this->collectionMeta->setTitle('Test Collection');
        $this->collectionMeta->setDescription('This Description is only for testing');
        $this->collectionMeta->setLocale('en-gb');
        $this->collectionMeta->setCollection($this->collection);

        $this->collection->addMeta($this->collectionMeta);

        // Collection Meta 2
        $collectionMeta2 = new CollectionMeta();
        $collectionMeta2->setTitle('Test Kollektion');
        $collectionMeta2->setDescription('Dies ist eine Test Beschreibung');
        $collectionMeta2->setLocale('de');
        $collectionMeta2->setCollection($this->collection);

        $this->collection->addMeta($collectionMeta2);

        $this->em->persist($this->collection);
        $this->em->persist($this->collectionType);
        $this->em->persist($this->collectionMeta);
        $this->em->persist($collectionMeta2);
    }

    /**
     * Test Media DownloadCounter.
     */
    public function testResponseHeader()
    {
        $date = new DateTime();
        $date->modify('+1 month');

        $client = $this->createAuthenticatedClient();

        $client->request(
            'GET',
            '/uploads/media/50x50/01/1-photo.jpeg'
        );

        $this->assertEquals($date->format('Y-m-d'), $client->getResponse()->getExpires()->format('Y-m-d'));
    }

    /**
     * Test Header dispositionType attachment
     */
    public function testDownloadHeaderAttachment()
    {
        $media = $this->createMedia('photo');

        $client = $this->createAuthenticatedClient();

        ob_start();
        $client->request(
            'GET',
            '/media/' . $media->getId() . '/download/photo.jpeg'
        );
        ob_end_clean();

        $this->assertEquals('attachment; filename="photo.jpeg"', $client->getResponse()->headers->get('Content-Disposition'));
    }

    /**
     * Test Header dispositionType inline
     */
    public function testDownloadHeaderInline()
    {
        $media = $this->createMedia('photo');

        $client = $this->createAuthenticatedClient();

        ob_start();
        $client->request(
            'GET',
            '/media/' . $media->getId() . '/download/photo.jpeg?inline=1'
        );
        ob_end_clean();

        $this->assertEquals('inline; filename="photo.jpeg"', $client->getResponse()->headers->get('Content-Disposition'));
    }

    /**
     * Test Media GET by ID.
     */
    public function testGetById()
    {
        $media = $this->createMedia('photo');
        $client = $this->createAuthenticatedClient();

        $client->request(
            'GET',
            '/api/media/' . $media->getId()
        );

        $this->assertEquals(200, $client->getResponse()->getStatusCode());

        $response = json_decode($client->getResponse()->getContent());

        $this->assertEquals($media->getId(), $response->id);
        $this->assertNotNull($response->type->id);
        $this->assertEquals('image', $response->type->name);
        $this->assertEquals('photo.jpeg', $response->name);
        $this->assertEquals($this->mediaDefaultTitle, $response->title);
        $this->assertEquals('2', $response->downloadCounter);
        $this->assertEquals($this->mediaDefaultDescription, $response->description);
        $this->assertNotEmpty($response->url);
        $this->assertNotEmpty($response->thumbnails);
    }

    /**
     * Test GET all Media.
     */
    public function testCget()
    {
        $media = $this->createMedia('photo');

        $client = $this->createAuthenticatedClient();

        $client->request(
            'GET',
            '/api/media'
        );

        $this->assertEquals(200, $client->getResponse()->getStatusCode());

        $response = json_decode($client->getResponse()->getContent());

        $this->assertNotEmpty($response);

        $this->assertEquals(1, $response->total);
    }

    /**
     * Test GET all Media.
     */
    public function testCgetCollection()
    {
        $media = $this->createMedia('photo');
        $client = $this->createAuthenticatedClient();

        $client->request(
            'GET',
            '/api/media?collection=' . $this->collection->getId()
        );

        $this->assertEquals(200, $client->getResponse()->getStatusCode());

        $response = json_decode($client->getResponse()->getContent());

        $this->assertNotEmpty($response);

        $this->assertEquals(1, $response->total);
        $this->assertEquals($media->getId(), $response->_embedded->media[0]->id);
        $this->assertEquals('photo.jpeg', $response->_embedded->media[0]->name);
    }

    /**
     * Test GET all Media.
     */
    public function testcGetCollectionTypes()
    {
        $media = $this->createMedia('photo');

        $client = $this->createAuthenticatedClient();

        $client->request(
            'GET',
            '/api/media?collection=' . $this->collection->getId() . '&types=image'
        );

        $this->assertEquals(200, $client->getResponse()->getStatusCode());

        $response = json_decode($client->getResponse()->getContent());

        $this->assertNotEmpty($response);

        $this->assertEquals(1, $response->total);
        $this->assertEquals($media->getId(), $response->_embedded->media[0]->id);
        $this->assertEquals('photo.jpeg', $response->_embedded->media[0]->name);
    }

    /**
     * Test GET all Media.
     */
    public function testcGetCollectionTypesNotExisting()
    {
        $client = $this->createAuthenticatedClient();

        $client->request(
            'GET',
            '/api/media?collection=' . $this->collection->getId() . '&types=audio'
        );

        $this->assertEquals(200, $client->getResponse()->getStatusCode());

        $response = json_decode($client->getResponse()->getContent());

        $this->assertNotEmpty($response);

        $this->assertEquals(0, $response->total);
    }

    /**
     * Test GET all Media.
     */
    public function testcGetCollectionTypesMultiple()
    {
        $media = $this->createMedia('photo');

        $client = $this->createAuthenticatedClient();

        $client->request(
            'GET',
            '/api/media?collection=' . $this->collection->getId() . '&types=image,audio'
        );

        $this->assertEquals(200, $client->getResponse()->getStatusCode());

        $response = json_decode($client->getResponse()->getContent());

        $this->assertNotEmpty($response);

        $this->assertEquals(1, $response->total);
        $this->assertEquals($media->getId(), $response->_embedded->media[0]->id);
        $this->assertEquals('photo.jpeg', $response->_embedded->media[0]->name);
    }

    /**
     * Test GET all Media.
     */
    public function testcGetIds()
    {
        $media = $this->createMedia('photo');

        $client = $this->createAuthenticatedClient();

        $client->request(
            'GET',
            '/api/media?ids=' . $media->getId()
        );

        $this->assertEquals(200, $client->getResponse()->getStatusCode());

        $response = json_decode($client->getResponse()->getContent());

        $this->assertNotEmpty($response);

        $this->assertEquals(1, $response->total);
        $this->assertEquals($media->getId(), $response->_embedded->media[0]->id);
        $this->assertEquals('photo.jpeg', $response->_embedded->media[0]->name);
    }

    public function testcGetMultipleIds()
    {
        $media1 = $this->createMedia('photo1');
        $media2 = $this->createMedia('photo2');

        $client = $this->createAuthenticatedClient();

        $client->request(
            'GET',
            '/api/media?ids=' . $media2->getId() . ',' . $media1->getId()
        );

        $this->assertEquals(200, $client->getResponse()->getStatusCode());

        $response = json_decode($client->getResponse()->getContent());

        $this->assertEquals(2, $response->total);
        $this->assertEquals($media2->getId(), $response->_embedded->media[0]->id);
        $this->assertEquals('photo2.jpeg', $response->_embedded->media[0]->name);
        $this->assertEquals($media1->getId(), $response->_embedded->media[1]->id);
        $this->assertEquals('photo1.jpeg', $response->_embedded->media[1]->name);
    }

    /**
     * Test GET all Media.
     */
    public function testcGetNotExistingIds()
    {
        $client = $this->createAuthenticatedClient();

        $client->request(
            'GET',
            '/api/media?ids=1232,3123,1234'
        );

        $this->assertEquals(200, $client->getResponse()->getStatusCode());

        $response = json_decode($client->getResponse()->getContent());

        $this->assertNotEmpty($response);

        $this->assertEquals(0, $response->total);
    }

    /**
     * Test GET for non existing Resource (404).
     */
    public function testGetByIdNotExisting()
    {
        $client = $this->createAuthenticatedClient();

        $client->request(
            'GET',
            '/api/media/11230'
        );

        $this->assertEquals(404, $client->getResponse()->getStatusCode());

        $response = json_decode($client->getResponse()->getContent());
        $this->assertEquals(5015, $response->code);
        $this->assertTrue(isset($response->message));
    }

    /**
     * Test POST to create a new Media with details.
     */
    public function testPost()
    {
        $client = $this->createAuthenticatedClient();

        $imagePath = $this->getImagePath();
        $this->assertTrue(file_exists($imagePath));
        $photo = new UploadedFile($imagePath, 'photo.jpeg', 'image/jpeg', 160768);

        $client->request(
            'POST',
            '/api/media',
            array(
                'collection' => $this->collection->getId(),
                'locale' => 'en-gb',
                'title' => 'New Image Title',
                'description' => 'New Image Description',
                'contentLanguages' => array(
                    'en-gb',
                ),
                'publishLanguages' => array(
                    'en-gb',
                    'en-au',
                    'en',
                    'de',
                ),
            ),
            array(
                'fileVersion' => $photo,
            )
        );

        $this->assertEquals(1, count($client->getRequest()->files->all()));

        $response = json_decode($client->getResponse()->getContent());

        $this->assertEquals(200, $client->getResponse()->getStatusCode());

        $this->assertEquals('photo.jpeg', $response->name);
        $this->assertNotNull($response->id);
        $this->assertEquals('en-gb', $response->locale);
        $this->assertEquals('photo.jpeg', $response->name);
        $this->assertEquals('New Image Title', $response->title);
        $this->assertEquals('New Image Description', $response->description);
        $this->assertNotEmpty($response->url);
        $this->assertNotEmpty($response->thumbnails);

        $this->assertEquals(array(
            'en-gb',
        ), $response->contentLanguages);

        $this->assertEquals(array(
            'en-gb',
            'en-au',
            'en',
            'de',
        ), $response->publishLanguages);
    }

    /**
     * Test POST to create a new Media without details.
     *
     * @group postWithoutDetails
     */
    public function testPostWithoutDetails()
    {
        $client = $this->createAuthenticatedClient();

        $imagePath = $this->getImagePath();
        $this->assertTrue(file_exists($imagePath));
        $photo = new UploadedFile($imagePath, 'photo.jpeg', 'image/jpeg', 160768);

        $client->request(
            'POST',
            '/api/media',
            array(
                'collection' => $this->collection->getId(),
            ),
            array(
                'fileVersion' => $photo,
            )
        );

        $this->assertEquals(1, count($client->getRequest()->files->all()));

        $response = json_decode($client->getResponse()->getContent());

        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertEquals($this->mediaDefaultTitle, $response->title);

        $this->assertEquals('photo.jpeg', $response->name);
        $this->assertNotNull($response->id);
    }

    /**
     * Test POST to create a new Media without details.
     *
     * @group postWithoutDetails
     */
    public function testPostWithSmallFile()
    {
        $client = $this->createAuthenticatedClient();

        $filePath = $this->getFilePath();
        $this->assertTrue(file_exists($filePath));
        $photo = new UploadedFile($filePath, 'small.txt', 'text/plain', 0);

        $client->request(
            'POST',
            '/api/media',
            array(
                'collection' => $this->collection->getId(),
            ),
            array(
                'fileVersion' => $photo,
            )
        );

        $this->assertEquals(1, count($client->getRequest()->files->all()));

        $response = json_decode($client->getResponse()->getContent());

        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertEquals('small', $response->title);

        $this->assertEquals('small.txt', $response->name);
        $this->assertNotNull($response->id);
    }

    /**
     * Test PUT to create a new FileVersion.
     */
    public function testFileVersionUpdate()
    {
        $media = $this->createMedia('photo');

        $client = $this->createAuthenticatedClient();

        $imagePath = $this->getImagePath();
        $this->assertTrue(file_exists($imagePath));
        $photo = new UploadedFile($imagePath, 'photo.jpeg', 'image/jpeg', 160768);

        $client->request(
            'POST',
            '/api/media/' . $media->getId() . '?action=new-version',
            array(
                'collection' => $this->collection->getId(),
                'locale' => 'en-gb',
                'title' => 'New Image Title',
                'description' => 'New Image Description',
                'contentLanguages' => array(
                    'en-gb',
                ),
                'publishLanguages' => array(
                    'en-gb',
                    'en-au',
                    'en',
                    'de',
                ),
            ),
            array(
                'fileVersion' => $photo,
            )
        );

        $this->assertEquals(1, count($client->getRequest()->files->all()));

        $response = json_decode($client->getResponse()->getContent());

        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertEquals($media->getId(), $response->id);
        $this->assertEquals($this->collection->getId(), $response->collection);
        $this->assertEquals(2, $response->version);
        $this->assertCount(2, (array) $response->versions);
        $this->assertNotEquals($response->versions->{'2'}->created, $response->versions->{'1'}->created);
        $this->assertEquals('en-gb', $response->locale);
        $this->assertEquals('New Image Title', $response->title);
        $this->assertEquals('New Image Description', $response->description);
        $this->assertEquals(array(
            'en-gb',
        ), $response->contentLanguages);
        $this->assertEquals(array(
            'en-gb',
            'en-au',
            'en',
            'de',
        ), $response->publishLanguages);
    }

    /**
     * Test PUT to create a new FileVersion.
     */
    public function testPutWithoutFile()
    {
        $media = $this->createMedia('photo');

        $client = $this->createAuthenticatedClient();

        $client->request(
            'PUT',
            '/api/media/' . $media->getId(),
            array(
                'collection' => $this->collection->getId(),
                'locale' => 'en-gb',
                'title' => 'Update Title',
                'description' => 'Update Description',
                'contentLanguages' => array(
                    'en-gb',
                ),
                'publishLanguages' => array(
                    'en-gb',
                    'en-au',
                    'en',
                    'de',
                ),
            )
        );

        $response = json_decode($client->getResponse()->getContent());

        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertEquals($media->getId(), $response->id);
        $this->assertEquals($this->collection->getId(), $response->collection);
        $this->assertEquals(1, $response->version);
        $this->assertCount(1, (array) $response->versions);
        $this->assertEquals('en-gb', $response->locale);
        $this->assertEquals('Update Title', $response->title);
        $this->assertEquals('Update Description', $response->description);
        $this->assertNotEmpty($response->url);
        $this->assertNotEmpty($response->thumbnails);
        $this->assertEquals(array(
            'en-gb',
        ), $response->contentLanguages);
        $this->assertEquals(array(
            'en-gb',
            'en-au',
            'en',
            'de',
        ), $response->publishLanguages);
    }

    /**
     * Test PUT to create a new FileVersion.
     */
    public function testFileVersionUpdateWithoutDetails()
    {
        $media = $this->createMedia('photo');

        $client = $this->createAuthenticatedClient();

        $imagePath = $this->getImagePath();
        $this->assertTrue(file_exists($imagePath));
        $photo = new UploadedFile($imagePath, 'photo.jpeg', 'image/jpeg', 160768);

        $client->request(
            'POST',
            '/api/media/' . $media->getId() . '?action=new-version',
            array(
                'collection' => $this->collection->getId(),
            ),
            array(
                'fileVersion' => $photo,
            )
        );

        $this->assertEquals(1, count($client->getRequest()->files->all()));

        $response = json_decode($client->getResponse()->getContent());

        $this->assertEquals(200, $client->getResponse()->getStatusCode());

        $this->assertEquals($media->getId(), $response->id);
        $this->assertEquals($this->mediaDefaultTitle, $response->title);
        $this->assertEquals($this->mediaDefaultDescription, $response->description);
        $this->assertEquals($this->collection->getId(), $response->collection);
        $this->assertEquals(2, $response->version);
        $this->assertCount(2, (array) $response->versions);
        $this->assertNotEmpty($response->url);
        $this->assertNotEmpty($response->thumbnails);
    }

    /**
     * Test DELETE.
     */
    public function testDeleteById()
    {
        $media = $this->createMedia('photo');

        $client = $this->createAuthenticatedClient();

        $client->request('DELETE', '/api/media/' . $media->getId());
        $this->assertNotNull($client->getResponse()->getStatusCode());

        $client = $this->createAuthenticatedClient();

        $client->request(
            'GET',
            '/api/media/' .  $media->getId()
        );

        $this->assertEquals(404, $client->getResponse()->getStatusCode());

        $response = json_decode($client->getResponse()->getContent());
        $this->assertEquals(5015, $response->code);
        $this->assertTrue(isset($response->message));
    }

    /**
     * Test DELETE Collection.
     */
    public function testDeleteCollection()
    {
        $media = $this->createMedia('photo');

        $client = $this->createAuthenticatedClient();

        $client->request('DELETE', '/api/collections/' . $this->collection->getId());
        $this->assertEquals('204', $client->getResponse()->getStatusCode());

        $client = $this->createAuthenticatedClient();

        $client->request(
            'GET',
            '/api/media/' . $media->getId()
        );

        $this->assertEquals(404, $client->getResponse()->getStatusCode());

        $response = json_decode($client->getResponse()->getContent());
        $this->assertEquals(5015, $response->code);
        $this->assertTrue(isset($response->message));
    }

    /**
     * Test DELETE on none existing Object.
     */
    public function testDeleteByIdNotExisting()
    {
        $media = $this->createMedia('photo');
        $client = $this->createAuthenticatedClient();

        $client->request('DELETE', '/api/media/404');
        $this->assertEquals('404', $client->getResponse()->getStatusCode());

        $client->request('GET', '/api/media');
        $response = json_decode($client->getResponse()->getContent());
        $this->assertEquals(1, $response->total);
    }

    /**
     * Test Media DownloadCounter.
     */
    public function testDownloadCounter()
    {
        $media = $this->createMedia('photo');

        $client = $this->createAuthenticatedClient();

        ob_start();
        $client->request(
            'GET',
            '/media/' . $media->getId() . '/download/photo.jpeg'
        );
        ob_end_clean();

        $this->assertEquals(200, $client->getResponse()->getStatusCode());

        $client->request(
            'GET',
            '/api/media/' . $media->getId()
        );

        $this->assertEquals(200, $client->getResponse()->getStatusCode());

        $response = json_decode($client->getResponse()->getContent());

        $this->assertEquals($media->getId(), $response->id);
        $this->assertNotNull($response->type->id);
        $this->assertEquals('image', $response->type->name);
        $this->assertEquals('photo.jpeg', $response->name);
        $this->assertEquals($this->mediaDefaultTitle, $response->title);
        $this->assertEquals('3', $response->downloadCounter);
        $this->assertEquals($this->mediaDefaultDescription, $response->description);
    }

    /**
     * Test move action
     */
    public function testMove()
    {
        $destCollection = new Collection();
        $style = array(
            'type' => 'circle',
            'color' => '#ffcc00',
        );

        $destCollection->setStyle(json_encode($style));
        $destCollection->setType($this->collectionType);
        $destCollection->addMeta($this->collectionMeta);

        $this->em->persist($destCollection);
        $this->em->flush();

        $media = $this->createMedia('photo');

        $client = $this->createAuthenticatedClient();
        $client->request(
            'POST',
            '/api/media/' . $media->getId() . '?action=move&destination=' . $destCollection->getId()
        );

        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertEquals($destCollection->getId(), $response['collection']);
        $this->assertEquals($this->mediaDefaultTitle, $response['title']);
    }

    /**
     * Test move to non existing collection
     */
    public function testMoveNonExistingCollection()
    {
        $media = $this->createMedia('photo');

        $client = $this->createAuthenticatedClient();
        $client->request('POST', '/api/media/' . $media->getId() . '?action=move&destination=404');

        $this->assertEquals(400, $client->getResponse()->getStatusCode());
    }

    /**
     * Test move to non existing media
     */
    public function testMoveNonExistingMedia()
    {
        $client = $this->createAuthenticatedClient();
        $client->request('POST', '/api/media/404?action=move&destination=' . $this->collection->getId());

        $this->assertEquals(404, $client->getResponse()->getStatusCode());
    }

    /**
     * Test non existing action
     */
    public function testMoveNonExistingAction()
    {
        $media = $this->createMedia('photo');

        $client = $this->createAuthenticatedClient();
        $client->request('POST', '/api/media/' . $media->getId() . '?action=test');

        $this->assertEquals(400, $client->getResponse()->getStatusCode());
    }

    /**
     * @return string
     */
    private function getImagePath()
    {
        return __DIR__ . '/../../app/Resources/images/photo.jpeg';
    }

    /**
     * @return string
     */
    private function getFilePath()
    {
        return __DIR__ . '/../../app/Resources/files/small.txt';
    }
}
