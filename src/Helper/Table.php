<?php
/*
 * This file is part of prooph/link.
 * (c) 2014-2015 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 * 
 * Date: 3/6/15 - 6:01 PM
 */
namespace Prooph\Link\SqlConnector\Helper;

/**
 * Class Table
 *
 * @package Prooph\Link\SqlConnector\Helper
 * @author Alexander Miertsch <kontakt@codeliner.ws>
 */
final class Table extends \Doctrine\DBAL\Schema\Table
{
    public static function rename(\Doctrine\DBAL\Schema\Table $table, $newName)
    {
        $table->_setName($newName);
    }
} 