<?php

namespace Kunstmaan\AdminBundle\Tests\Helper\Security\Acl;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\QuoteStrategy;
use Doctrine\ORM\Query\AST\FromClause;
use Doctrine\ORM\Query\AST\IdentificationVariableDeclaration;
use Doctrine\ORM\Query\AST\IndexBy;
use Doctrine\ORM\Query\AST\PathExpression;
use Doctrine\ORM\Query\AST\RangeVariableDeclaration;
use Doctrine\ORM\Query\ParserResult;
use Doctrine\ORM\Query\ResultSetMapping;
use Kunstmaan\AdminBundle\Helper\Security\Acl\AclWalker;
use PHPUnit\Framework\TestCase;

class AclWalkerTest extends TestCase
{
    public function testWalker()
    {
        $range = new RangeVariableDeclaration('someschema', 's');
        $expr = new PathExpression('int', 'id', 'id');
        $expr->type = PathExpression::TYPE_STATE_FIELD;
        $indexBy = new IndexBy($expr);
        $from = new FromClause([new IdentificationVariableDeclaration($range, $indexBy, [])]);

        $meta = $this->createMock(ClassMetadata::class);
        $strategy = $this->createMock(QuoteStrategy::class);
        $config = $this->createMock(Configuration::class);

        $platform = $this->createMock(AbstractPlatform::class);
        $platform->expects($this->once())->method('appendLockHint')->willReturn('someschema s');

        $conn = $this->createMock(Connection::class);
        $conn->expects($this->any())->method('getDatabasePlatform')->willReturn($platform);

        $em = $this->createMock(EntityManager::class);
        $query = $this->createMock(AbstractQuery::class);
        $mapping = $this->createMock(ResultSetMapping::class);
        $result = $this->createMock(ParserResult::class);

        $meta->expects($this->once())->method('getTableName')->willReturn('sometable');
        $strategy->expects($this->once())->method('getTableName')->willReturn('sometable');
        $config->expects($this->once())->method('getQuoteStrategy')->willReturn($strategy);

        $query->expects($this->once())->method('getEntityManager')->willReturn($em);
        $query->expects($this->exactly(4))->method('getHint')->will($this->onConsecutiveCalls('sometable', 'sometable', 's', null));
        $em->expects($this->once())->method('getConnection')->willReturn($conn);
        $em->expects($this->once())->method('getConfiguration')->willReturn($config);
        $em->expects($this->once())->method('getClassMetaData')->willReturn($meta);
        $result->expects($this->once())->method('getResultSetMapping')->willReturn($mapping);

        $aclWalker = new AclWalker($query, $result, []);
        $sql = $aclWalker->walkFromClause($from);

        $expectedRegex = '/(JOIN \(\) ta_ ON s0_\.id = ta_.id)$/';
        method_exists($this, 'assertMatchesRegularExpression') ? $this->assertMatchesRegularExpression($expectedRegex, $sql) : $this->assertRegExp($expectedRegex, $sql);
    }
}
