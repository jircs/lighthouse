<?php

namespace Tests\Integration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\DBTestCase;
use Tests\Utils\Models\Task;
use Tests\Utils\Models\User;

class GraphQLTest extends DBTestCase
{
    use RefreshDatabase;

    /**
     * Auth user.
     *
     * @var User
     */
    protected $user;

    /**
     * User assigned tasks.
     *
     * @var \Illuminate\Support\Collection
     */
    protected $tasks;

    /**
     * Define environment setup.
     *
     * @param \Illuminate\Foundation\Application $app
     */
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $path = $this->store('schema.graphql', '
        type User {
            id: ID!
            name: String!
            email: String!
            created_at: String!
            updated_at: String!
            tasks: [Task!]! @hasMany
        }
        type Task {
            id: ID!
            name: String!
            created_at: String!
            updated_at: String!
            user: User! @belongsTo
        }
        type Query {
            user: User @auth
        }
        ');

        $app['config']->set('lighthouse.schema.register', $path);
    }

    /**
     * Setup test environment.
     */
    protected function setUp()
    {
        parent::setUp();

        $this->user = factory(User::class)->create();
        $this->tasks = factory(Task::class, 5)->create([
            'user_id' => $this->user->getKey(),
        ]);
    }

    /**
     * @test
     */
    public function itCanResolveQuery()
    {
        $this->be($this->user);
        $query = '
        query UserWithTasks {
            user {
                email
                tasks {
                    name
                }
            }
        }
        ';

        $data = graphql()->execute($query);
        $expected = [
            'data' => [
                'user' => [
                    'email' => $this->user->email,
                    'tasks' => $this->tasks->map(function ($task) {
                        return ['name' => $task->name];
                    })->toArray(),
                ],
            ],
        ];

        $this->assertEquals($expected, $data);
    }


    /**
     * @test
     */
    public function itCanResolveQueryThroughController()
    {
        $this->be($this->user);
        $query = '
        query UserWithTasks {
            user {
                email
                tasks {
                    name
                }
            }
        }
        ';

        $data = $this->postJson("graphql", ['query' => $query])->json();

        $expected = [
            'data' => [
                'user' => [
                    'email' => $this->user->email,
                    'tasks' => $this->tasks->map(function ($task) {
                        return ['name' => $task->name];
                    })->toArray(),
                ],
            ],
        ];

        $this->assertEquals($expected, $data);
    }
}