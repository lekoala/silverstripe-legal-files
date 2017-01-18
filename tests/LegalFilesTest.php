<?php

/**
 * Tests for Legal Files modules
 */
class LegalFilesTest extends SapphireTest
{

    protected static $fixture_file = 'LegalFilesTest.yml';
    protected $usesDatabase = true;

    public function testTypesCount()
    {
        $types = LegalFileType::get();

        $this->assertEquals(3, $types->count());
    }

    public function testExpiration()
    {
        $doc = new LegalFile;
        $doc->ExpirationDate = date('Y-m-d', strtotime('-2 weeks'));

        $this->assertTrue($doc->IsExpired());

        $doc = new LegalFile;
        $doc->ExpirationDate = date('Y-m-d', strtotime('+2 weeks'));

        $this->assertFalse($doc->IsExpired());
    }

    public function testExpiredQuery()
    {
        $files = LegalFile::get()
            ->filter('ExpirationDate:LessThan', date('Y-m-d', strtotime('+ 15 days')))
            ->exclude('MemberID', 0)
            ->where('Reminded IS NULL') // In 3.1 filter = null is not working
        ;


        $this->assertNotEquals(0, $files->count());
    }

    public function testInvalidEmail()
    {
        /* @var $waitingFile LegalFile */
        $waitingFile = LegalFile::get()->filter('Status', LegalFile::STATUS_WAITING)->first();

        $waitingFile->doInvalid();

        $this->assertTrue($waitingFile->Status == LegalFile::STATUS_INVALID);
    }
}
