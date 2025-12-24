<?php
/**
 * 性能优化数据库迁移
 * 添加索引以提升查询性能
 */

use yii\db\Migration;

class m251225_000000_performance_indexes extends Migration
{
    public function safeUp()
    {
        // 用户表索引
        $this->createIndex(
            'idx-user-email',
            '{{%user}}',
            'email'
        );
        
        $this->createIndex(
            'idx-user-status-created',
            '{{%user}}',
            ['status', 'created_at']
        );
        
        // 主题表索引
        $this->createIndex(
            'idx-topic-node-replied',
            '{{%topic}}',
            ['node_id', 'replied_at']
        );
        
        $this->createIndex(
            'idx-topic-user-created',
            '{{%topic}}',
            ['user_id', 'created_at']
        );
        
        $this->createIndex(
            'idx-topic-alltop-replied',
            '{{%topic}}',
            ['alltop', 'replied_at']
        );
        
        // 评论表索引
        $this->createIndex(
            'idx-comment-topic-position',
            '{{%comment}}',
            ['topic_id', 'position']
        );
        
        $this->createIndex(
            'idx-comment-user-created',
            '{{%comment}}',
            ['user_id', 'created_at']
        );
        
        // 通知表索引
        $this->createIndex(
            'idx-notice-target-status',
            '{{%notice}}',
            ['target_id', 'status']
        );
        
        $this->createIndex(
            'idx-notice-type-status',
            '{{%notice}}',
            ['type', 'status', 'target_id']
        );
        
        // Token表索引
        $this->createIndex(
            'idx-token-user-type-status',
            '{{%token}}',
            ['user_id', 'type', 'status']
        );
        
        $this->createIndex(
            'idx-token-expires',
            '{{%token}}',
            'expires'
        );
        
        echo "性能优化索引已添加成功\n";
    }

    public function safeDown()
    {
        // 用户表索引
        $this->dropIndex('idx-user-email', '{{%user}}');
        $this->dropIndex('idx-user-status-created', '{{%user}}');
        
        // 主题表索引
        $this->dropIndex('idx-topic-node-replied', '{{%topic}}');
        $this->dropIndex('idx-topic-user-created', '{{%topic}}');
        $this->dropIndex('idx-topic-alltop-replied', '{{%topic}}');
        
        // 评论表索引
        $this->dropIndex('idx-comment-topic-position', '{{%comment}}');
        $this->dropIndex('idx-comment-user-created', '{{%comment}}');
        
        // 通知表索引
        $this->dropIndex('idx-notice-target-status', '{{%notice}}');
        $this->dropIndex('idx-notice-type-status', '{{%notice}}');
        
        // Token表索引
        $this->dropIndex('idx-token-user-type-status', '{{%token}}');
        $this->dropIndex('idx-token-expires', '{{%token}}');
        
        echo "性能优化索引已移除\n";
    }
}
