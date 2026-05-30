<?php

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('guests are redirected to the login page for documents index', function () {
    $this->get(route('documents'))
        ->assertRedirect(route('login'));
});

test('guests are redirected to the login page for document show', function () {
    $document = Document::factory()->create();

    $this->get(route('documents.show', $document))
        ->assertRedirect(route('login'));
});

test('authenticated users can visit the documents index', function () {
    $user = User::factory()->create();
    Document::factory()->for($user)->count(3)->create();

    $this->actingAs($user)
        ->get(route('documents'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('documents')
            ->has('documents.data', 3)
            ->where('selectedDocument', null)
        );
});

test('documents index only shows documents belonging to the authenticated user', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    Document::factory()->for($user)->count(2)->create();
    Document::factory()->for($other)->count(5)->create();

    $this->actingAs($user)
        ->get(route('documents'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('documents.data', 2)
        );
});

test('authenticated users can view a document detail', function () {
    $user = User::factory()->create();
    $document = Document::factory()->for($user)->create([
        'title' => 'My Spec Document',
        'document_type' => 'spec',
        'status' => 'draft',
        'summary' => 'A brief summary.',
    ]);

    $this->actingAs($user)
        ->get(route('documents.show', $document))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('documents')
            ->where('selectedDocument.id', $document->id)
            ->where('selectedDocument.title', 'My Spec Document')
            ->where('selectedDocument.documentType', 'spec')
            ->where('selectedDocument.tags', [])
            ->where('selectedDocument.summary', 'A brief summary.')
            ->has('selectedDocument.scraps')
        );
});

test('users cannot view documents belonging to other users', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $document = Document::factory()->for($other)->create();

    $this->actingAs($user)
        ->get(route('documents.show', $document))
        ->assertForbidden();
});

test('authenticated users can delete their own document', function () {
    $user = User::factory()->create();
    $document = Document::factory()->for($user)->create();

    $this->actingAs($user)
        ->delete(route('documents.destroy', $document))
        ->assertRedirect(route('documents'));

    $this->assertModelMissing($document);
});

test('users cannot delete documents belonging to other users', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $document = Document::factory()->for($other)->create();

    $this->actingAs($user)
        ->delete(route('documents.destroy', $document))
        ->assertForbidden();

    $this->assertModelExists($document);
});

test('authenticated users can update a document including tags', function () {
    $user = User::factory()->create();
    $document = Document::factory()->for($user)->create([
        'title' => 'Old Title',
        'content_markdown' => 'Old content.',
    ]);

    $this->actingAs($user)
        ->patch(route('documents.update', $document), [
            'title' => 'New Title',
            'content_markdown' => 'New content.',
            'tags' => ['design', 'api'],
        ])
        ->assertRedirect(route('documents.show', $document));

    $document->refresh();
    expect($document->title)->toBe('New Title');
    expect($document->content_markdown)->toBe('New content.');
    expect($document->meta['tags'])->toBe(['design', 'api']);
});

test('users cannot update documents belonging to other users', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $document = Document::factory()->for($other)->create();

    $this->actingAs($user)
        ->patch(route('documents.update', $document), [
            'title' => 'Hacked',
            'content_markdown' => 'x',
            'tags' => [],
        ])
        ->assertForbidden();
});

test('guests are redirected to login for document pdf', function () {
    $document = Document::factory()->create();

    $this->get(route('documents.pdf', $document))
        ->assertRedirect(route('login'));
});

test('authenticated users can download their document as pdf', function () {
    $user = User::factory()->create();
    $document = Document::factory()->for($user)->create([
        'title' => 'My Test Document',
        'document_type' => 'spec',
        'content_markdown' => '# Hello World',
        'summary' => 'A test summary.',
    ]);

    $response = $this->actingAs($user)
        ->get(route('documents.pdf', $document))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    expect((string) $response->headers->get('content-disposition'))
        ->toContain('attachment;')
        ->toContain('my-test-document.pdf');
});

test('users cannot download pdf of documents belonging to other users', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $document = Document::factory()->for($other)->create();

    $this->actingAs($user)
        ->get(route('documents.pdf', $document))
        ->assertForbidden();
});

test('documents index can be filtered by tag', function () {
    $user = User::factory()->create();
    Document::factory()->for($user)->create(['meta' => ['tags' => ['design']]]);
    Document::factory()->for($user)->create(['meta' => ['tags' => ['api']]]);

    $this->actingAs($user)
        ->get(route('documents', ['tag' => 'design']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('documents.data', 1)
            ->where('activeTag', 'design')
        );
});
