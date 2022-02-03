<?php

namespace ACPL\PostValidator\Console;

use Exception;
use Flarum\Console\AbstractCommand;
use Flarum\Http\UrlGenerator;
use Flarum\Post\CommentPost;
use Symfony\Component\Console\Input\InputOption;
use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Contracts\Filesystem\Filesystem;

class ValidatePosts extends AbstractCommand
{
    private UrlGenerator $url;
    private Filesystem $uploadDir;

    public function __construct(UrlGenerator $url, Factory $filesystemFactory)
    {
        parent::__construct();
        $this->url = $url;
        $this->uploadDir = $filesystemFactory->disk('flarum-assets');
    }

    protected function configure()
    {
        $this
            ->setName('validate-posts')
            ->setDescription('Validates all posts in the database')
            ->addOption('chunk', null, InputOption::VALUE_REQUIRED);
    }

    protected function fire()
    {
        $this->info('Validating all posts. This can take a while.');
        $invalid = [];
        CommentPost::query()->select()->where('type', '=', 'comment')->chunk($this->input->getOption('chunk') ?? 1000,
            function ($posts) use (&$invalid) {
                foreach ($posts as $post) {
                    try {
                        $post->formatContent();
                    } catch (Exception $e) {
                        $invalid[] = [
                            'id' => $post->id,
                            'url' => $this->url->to('forum')->route('discussion',
                                ['id' => $post->discussion_id, 'near' => $post->number]),
                        ];
                    }
                }
            });

        $this->info(count($invalid).' invalid posts found');

        if (count($invalid)) {
            $this->uploadDir->makeDirectory('invalid-posts');
            $fileName = 'invalid-posts/invalid-posts-'.time().'.json';
            $this->uploadDir->put($fileName, json_encode($invalid));

            $this->info('List was saved to: /public/assets/'.$fileName);
        }
    }
}
