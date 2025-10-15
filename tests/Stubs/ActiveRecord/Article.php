<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecord;

final class Article extends ActiveRecord
{
    protected int $id;
    protected string $title;
    protected string $slug;

    public function tableName(): string
    {
        return 'article';
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): void
    {
        $this->slug = $slug;
    }

    public function relationQuery(string $name): ActiveQueryInterface
    {
        return match ($name) {
            'comments' => $this->getCommentsQuery(),
            default => parent::relationQuery($name),
        };
    }

    public function getComments(): array
    {
        return $this->relation('comments');
    }

    public function getCommentsQuery(): ActiveQuery
    {
        return $this->hasMany(ArticleComment::class, ['article_slug' => 'slug']);
    }
}
