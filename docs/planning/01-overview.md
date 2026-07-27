# Overview

## Problem

Tracking what you've watched, played, and read is scattered across single-purpose apps: Letterboxd for films, Trakt for TV, Backloggd for games, StoryGraph or Goodreads for books. None of them cover all four, and most push you toward a paid tier or a social network you didn't ask for. There's no good, free, all-in-one option for a small group of friends or a household who just want to log what they've finished, rate it, and remember what they thought.

## What Done is

A single, self-hosted app where a small, invited group of people can:

- Log movies, TV shows, games, and books they've completed (or are in progress on, or plan to get to)
- Rate each on a 0–5 scale with half-star precision
- Write a review, optionally flagged as containing spoilers
- Mark favourites
- Track rewatches/replays/rereads as their own dated entries
- See (permission-allowing) what other members of the group have logged

## Goals (v1)

- Cover all four media types with type-appropriate metadata (seasons/episodes for TV, platforms for games, authors/page count for books, etc.)
- Diary-style logging so history isn't lost on a rewatch
- Backlog/watchlist support, not just a record of completed items
- Invite-only access with basic role-based permissions
- Mobile-first UI — most logging happens on a phone right after finishing something
- Self-hostable by one person on a single small VPS

## Non-goals (v1)

- Public signups / open registration
- Large-scale social network features (follows, comments, likes, DMs) — a simple activity feed is in scope, a full social graph is not
- Multi-tenant SaaS hosting (this is one instance per group, not a hosted product for many separate groups)
- Anime/manga, podcasts, and music as distinct types — noted as a plausible fast-follow, out of scope for v1

## Target users

A small, known group of people (friends, family, a household) invited by an admin. Expected scale: well under 50 users, self-hosted on a single VPS. This shapes several downstream decisions — see [05-database-choice.md](05-database-choice.md) and [08-architecture-deployment.md](08-architecture-deployment.md).
