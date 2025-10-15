<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecord;

final class ArticleComment extends ActiveRecord
{
    protected int $id;
    protected string $article_slug;
    protected string $comment_text;

    public function tableName(): string
    {
        return 'article_comment';
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getArticleSlug(): string
    {
        return $this->article_slug;
    }

    public function setArticleSlug(string $slug): void
    {
        $this->article_slug = $slug;
    }

    public function getCommentText(): string
    {
        return $this->comment_text;
    }

    public function setCommentText(string $text): void
    {
        $this->comment_text = $text;
    }

    public function relationQuery(string $name): ActiveQueryInterface
    {
        return match ($name) {
            'article' => $this->getArticleQuery(),
            default => parent::relationQuery($name),
        };
    }

    public function getArticle(): Article|null
    {
        return $this->relation('article');
    }

    public function getArticleQuery(): ActiveQuery
    {
        return $this->hasOne(Article::class, ['slug' => 'article_slug']);
    }
}
