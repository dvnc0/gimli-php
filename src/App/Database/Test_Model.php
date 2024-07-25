<?php
declare(strict_types=1);

namespace Gimli\Database;

use Gimli\Database\Model;
use Gimli\Database\Seed;

class Test_Model extends Model {
	
	/**
	 * @var string $table_name
	 */
	protected string $table_name = 'test_table';

	
	/**
	 * ID
	 * 
	 * @var int $id 
	 */
	public $id;

	/**
	 * Unique_Id
	 * 
	 * @var string $unique_id 
	 */
	#[Seed(type: 'unique_id', args: ['length' => 12])]
	public $unique_id;

	/**
	 * Username
	 * 
	 * @var string $username 
	 */
	#[Seed(type: 'username')]
	public $username;

	/**
	 * Email
	 * 
	 * @var string $email 
	 */
	#[Seed(type: 'email')]
	public $email;

	/**
	 * Password
	 * 
	 * @var string $password 
	 */
	#[Seed(type: 'password')]
	public $password;

	/**
	 * Salt
	 * 
	 * @var int $salt 
	 */
	#[Seed(type: 'one_of', args: [1,2,3,4])]
	public $salt;

	/**
	 * Is_Active
	 * 
	 * @var int $is_active 
	 */
	#[Seed(type: 'one_of', args: [0,1])]
	public $is_active;

	/**
	 * Tier
	 * 
	 * @var int $tier 
	 */
	#[Seed(type: 'one_of', args: [1,2,3])]
	public $tier;

	/**
	 * First Name
	 * 
	 * @var string $first_name 
	 */
	#[Seed(type: 'first_name')]
	public $first_name;

	/**
	 * Last Name
	 * 
	 * @var string $last_name 
	 */
	#[Seed(type: 'last_name')]
	public $last_name;

	/**
	 * Status
	 * 
	 * @var int $status 
	 */
	#[Seed(type: 'one_of', args: [0,1])]
	public $status;

	/**
	 * Created_At
	 * 
	 * @var string $created_at 
	 */
	#[Seed(type: 'date', args: ['format' => 'Y-m-d H:i:s', 'min' => '2021-01-01', 'max' => '2021-04-01 00:00:00'])]
	public $created_at;

	/**
	 * Updated_At
	 * 
	 * @var string $updated_at 
	 */
	#[Seed(type: 'date', args: ['format' => 'Y-m-d H:i:s', 'min' => '2021-04-01 00:02:00'])]
	public $updated_at;

	#[Seed(type: 'paragraph', args: ['count' => 1])]
	public $about;
}