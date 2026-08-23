<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CRM refonte : champs prospects/activités + table opportunités.
 * Réutilise prospects + clients existants, sans dupliquer l’ERP.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('tenant');

        if ($schema->hasTable('prospects')) {
            $schema->table('prospects', function (Blueprint $table) use ($schema) {
                if (! $schema->hasColumn('prospects', 'first_name')) {
                    $table->string('first_name', 120)->nullable()->after('name');
                }
                if (! $schema->hasColumn('prospects', 'last_name')) {
                    $table->string('last_name', 120)->nullable()->after('first_name');
                }
                if (! $schema->hasColumn('prospects', 'company_name')) {
                    $table->string('company_name')->nullable()->after('last_name');
                }
                if (! $schema->hasColumn('prospects', 'job_title')) {
                    $table->string('job_title', 120)->nullable()->after('company_name');
                }
                if (! $schema->hasColumn('prospects', 'whatsapp')) {
                    $table->string('whatsapp', 40)->nullable()->after('phone');
                }
                if (! $schema->hasColumn('prospects', 'city')) {
                    $table->string('city', 120)->nullable()->after('address');
                }
                if (! $schema->hasColumn('prospects', 'sector')) {
                    $table->string('sector', 120)->nullable()->after('city');
                }
                if (! $schema->hasColumn('prospects', 'need')) {
                    $table->string('need', 180)->nullable()->after('notes');
                }
                if (! $schema->hasColumn('prospects', 'product_interest')) {
                    $table->string('product_interest', 180)->nullable()->after('need');
                }
                if (! $schema->hasColumn('prospects', 'problem')) {
                    $table->text('problem')->nullable()->after('product_interest');
                }
                if (! $schema->hasColumn('prospects', 'expectations')) {
                    $table->text('expectations')->nullable()->after('problem');
                }
                if (! $schema->hasColumn('prospects', 'decision_maker_name')) {
                    $table->string('decision_maker_name', 120)->nullable()->after('expectations');
                }
                if (! $schema->hasColumn('prospects', 'need_score')) {
                    $table->unsignedTinyInteger('need_score')->default(0)->after('decision_maker_name');
                }
                if (! $schema->hasColumn('prospects', 'decision_score')) {
                    $table->unsignedTinyInteger('decision_score')->default(0)->after('need_score');
                }
                if (! $schema->hasColumn('prospects', 'budget_score')) {
                    $table->unsignedTinyInteger('budget_score')->default(0)->after('decision_score');
                }
                if (! $schema->hasColumn('prospects', 'timeline_score')) {
                    $table->unsignedTinyInteger('timeline_score')->default(0)->after('budget_score');
                }
                if (! $schema->hasColumn('prospects', 'interaction_score')) {
                    $table->unsignedTinyInteger('interaction_score')->default(0)->after('timeline_score');
                }
                if (! $schema->hasColumn('prospects', 'score')) {
                    $table->unsignedTinyInteger('score')->default(0)->after('interaction_score');
                }
                if (! $schema->hasColumn('prospects', 'estimated_budget')) {
                    $table->decimal('estimated_budget', 14, 2)->nullable()->after('score');
                }
                if (! $schema->hasColumn('prospects', 'decision_deadline')) {
                    $table->date('decision_deadline')->nullable()->after('estimated_budget');
                }
                if (! $schema->hasColumn('prospects', 'last_contacted_at')) {
                    $table->timestamp('last_contacted_at')->nullable()->after('decision_deadline');
                }
                if (! $schema->hasColumn('prospects', 'is_favorite')) {
                    $table->boolean('is_favorite')->default(false)->after('last_contacted_at');
                }
            });

            try {
                $schema->table('prospects', function (Blueprint $table) {
                    $table->index('score', 'prospects_score_idx');
                });
            } catch (\Throwable) {
            }
        }

        if ($schema->hasTable('prospect_activities')) {
            $schema->table('prospect_activities', function (Blueprint $table) use ($schema) {
                if (! $schema->hasColumn('prospect_activities', 'opportunity_id')) {
                    $table->unsignedBigInteger('opportunity_id')->nullable()->after('prospect_id');
                }
                if (! $schema->hasColumn('prospect_activities', 'result')) {
                    $table->string('result', 255)->nullable()->after('body');
                }
            });
            try {
                $schema->table('prospect_activities', function (Blueprint $table) {
                    $table->index('opportunity_id', 'prospect_activities_opportunity_idx');
                });
            } catch (\Throwable) {
            }
        }

        if (! $schema->hasTable('crm_opportunities')) {
            $schema->create('crm_opportunities', function (Blueprint $table) {
                $table->id();
                $table->foreignId('prospect_id')->constrained('prospects')->cascadeOnDelete();
                $table->unsignedBigInteger('client_id')->nullable();
                $table->string('title');
                $table->string('product_interest', 180)->nullable();
                $table->decimal('amount', 14, 2)->nullable();
                $table->unsignedTinyInteger('probability')->default(20);
                $table->string('stage', 30)->default('qualification');
                $table->unsignedBigInteger('owner_id')->nullable();
                $table->date('expected_close_date')->nullable();
                $table->string('lost_reason', 80)->nullable();
                $table->text('lost_comment')->nullable();
                $table->boolean('starred')->default(false);
                $table->timestamp('transferred_at')->nullable();
                $table->unsignedBigInteger('quotation_id')->nullable();
                $table->timestamp('won_at')->nullable();
                $table->timestamp('lost_at')->nullable();
                $table->timestamp('last_activity_at')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();

                $table->index('stage');
                $table->index('owner_id');
                $table->index('client_id');
                $table->index(['stage', 'owner_id']);
            });
        }

        $this->remapLegacyPipeline();
    }

    public function down(): void
    {
        $schema = Schema::connection('tenant');
        $schema->dropIfExists('crm_opportunities');
    }

    private function remapLegacyPipeline(): void
    {
        if (! Schema::connection('tenant')->hasTable('prospects')
            || ! Schema::connection('tenant')->hasTable('crm_opportunities')) {
            return;
        }

        $now = now();

        // Statuts prospects : pipeline historique → cycle de vie lead.
        DB::connection('tenant')->table('prospects')->orderBy('id')->chunkById(100, function ($rows) use ($now) {
            foreach ($rows as $row) {
                $oldStatus = (string) $row->status;
                $newStatus = match ($oldStatus) {
                    'nouveau', 'contacte' => 'nouveau',
                    'qualifie', 'negociation', 'gagne' => 'qualifie',
                    'converti' => 'converti',
                    'perdu' => 'non_qualifie',
                    'a_qualifier', 'non_qualifie' => $oldStatus,
                    default => 'nouveau',
                };

                $company = $row->type === 'company' ? $row->name : ($row->company_name ?? null);
                $score = (int) ($row->score ?? 0);

                DB::connection('tenant')->table('prospects')->where('id', $row->id)->update([
                    'status' => $newStatus,
                    'company_name' => $company ?: $row->company_name,
                    'whatsapp' => $row->whatsapp ?: $row->phone,
                    'score' => $score,
                    'updated_at' => $now,
                ]);

                $shouldCreateOpp = in_array($oldStatus, ['qualifie', 'negociation', 'gagne', 'converti', 'perdu'], true);
                $already = DB::connection('tenant')->table('crm_opportunities')->where('prospect_id', $row->id)->exists();
                if (! $shouldCreateOpp || $already) {
                    continue;
                }

                $stage = match ($oldStatus) {
                    'qualifie' => 'qualification',
                    'negociation' => 'suivi',
                    'gagne', 'converti' => 'gagne',
                    'perdu' => 'perdu',
                    default => 'qualification',
                };

                $probability = match ($stage) {
                    'qualification' => 20,
                    'qualifiee' => 35,
                    'opportunite' => 50,
                    'suivi' => 65,
                    'intention' => 80,
                    'gagne' => 100,
                    'perdu' => 0,
                    default => 20,
                };

                DB::connection('tenant')->table('crm_opportunities')->insert([
                    'prospect_id' => $row->id,
                    'client_id' => $row->converted_client_id,
                    'title' => $row->need ?: ($row->name ?: 'Opportunité'),
                    'product_interest' => $row->need,
                    'amount' => $row->expected_value,
                    'probability' => $probability,
                    'stage' => $stage,
                    'owner_id' => $row->owner_id,
                    'expected_close_date' => null,
                    'lost_reason' => $oldStatus === 'perdu' ? ($row->lost_reason ?: 'autre') : null,
                    'lost_comment' => $oldStatus === 'perdu' ? $row->lost_reason : null,
                    'starred' => false,
                    'won_at' => in_array($stage, ['gagne'], true) ? ($row->converted_at ?: $now) : null,
                    'lost_at' => $stage === 'perdu' ? $row->updated_at : null,
                    'last_activity_at' => $row->updated_at,
                    'created_by' => $row->created_by,
                    'updated_by' => $row->updated_by,
                    'created_at' => $row->created_at ?: $now,
                    'updated_at' => $now,
                ]);
            }
        });
    }
};
