# **MemberPress Courses AI-Copilot — Discussion document**

## **1\) Overview (What we’re proposing)**

**Courses AI-Copilot** is a proposal to add a conversational AI assistant inside MemberPress Courses so creators can go from a course idea to a complete, well-structured **outline with AI-generated draft lesson content** in minutes.

The assistant would ask targeted questions (topic, audience, objectives, scope), generate a full outline with best-practice pedagogy and suggested lesson text, show a live preview for quick edits, and, once approved, programmatically create the course structure and draft content in MemberPress.

**What this unlocks**

* Lowers the “activation energy” for launching courses (people who want a side course but don’t have time/know-how).

* Raises the baseline quality of course structures and lesson content from day one.

* Creates a differentiated experience versus competitors by making creation a lot easier.

* Establishes a foundation for future AI features (expanded content, assessments, personalization).

**Competitive angle:** a WordPress-native, fully integrated AI course builder positions MemberPress with a strong, defensible differentiator.

---

## 

## **2\) Project Objective (Why we’re doing it & what “good” looks like)**

* **Primary**: Reduce course-structuring and initial content creation time from \~6–10 hours to \~10–30 minutes while improving consistency and pedagogy.

* **Secondary:** Make it dramatically easier to achieve a high-quality, professional outline and initial content—reducing the barrier to entry and creating a more inviting starting point, thereby increasing the likelihood that customers proceed to develop and complete their courses.

* **Context (short):** This also kick-starts our adaptation to the current AI wave. Modernizing our product footprint with a practical, high-impact use case.

---

## **3\) How it would work** 

**Entry points**

* Courses → All Courses: **Create with AI** button next to “Add New”

* Courses → Add New: **Use AI Assistant** toggle above the title

**Core flow**

1. **Conversation kickoff**: AI welcomes user and offers course templates (Technical, Business, Creative, Academic, Other).

2. **Scoping**: collects topic, audience, learning objectives, desired scope/duration.

3. **Outline \+ content generation**: AI produces course title, modules, lessons, estimated durations, and **draft text for each lesson**. Preview updates in real time.

4. **Refinement loop (optional)**:

   * Add learning objectives/resources/exercises/quizzes per module.

   * Regenerate specific sections or lesson content.

   * Inline edit titles, descriptions, and draft lesson text; drag-and-drop lessons.

5. **Create course**: one click writes the structure and draft content into MemberPress (course, sections, lessons) ready for review and publishing.

**UX characteristics**

* Two-panel layout: chat on the left, live outline \+ content preview on the right.

* Quick actions: Undo, Skip, Help, Save progress.

* Accessibility: keyboard navigation, screen-reader labels, high-contrast option.

* Mobile: condensed preview, touch-friendly actions, voice input option.

**Architecture (summary)**

* **Frontend**: React app embedded in WP Admin. State machine for conversation steps; context \+ localStorage for persistence.

* **AI proxy**: WP REST endpoint as server-side proxy to multiple LLMs (provider selection, fallback, rate limiting, response validation/caching).

* **Course creation**: uses existing MemberPress APIs/functions to create course/sections/lessons with draft content for full compatibility (access rules, ReadyLaunch™, etc.).

* **Data model (additions)**:

  * mp\_ai\_conversations: session metadata, conversation history (JSON), generated structure \+ content, status.

  * mp\_ai\_usage\_logs: provider, request type, tokens, latency, cost.

  * Flags on courses: ai\_generated, ai\_conversation\_id.

* **Security & privacy**: server-side API keys; capability checks; input/output sanitization; strict escaping; prepared statements; per-user/IP/global rate limits; user-controlled conversation retention.

* **Ops**: monitoring of response times, conversation completion rate, cost metrics; i18n-ready; WCAG-aligned.

---

## **4\) Key Benefits**

**For customers**

* **Ship faster**: minutes to a professional outline \+ draft lesson content.

* **Better pedagogy by default**: logical progression, balanced modules, relevant lesson material.

* **Easier to refine**: regenerate sections or lesson text, add exercises/objectives quickly.

* **Lowers the barrier**: makes course creation accessible to more people.

**For MemberPress**

* **Differentiation** vs. competitors (native, integrated AI flow).

* **Growth levers**: potential premium tier, reduced churn via quicker time-to-value, stronger onboarding.

* Data **foundation** for future AI features (content expansion, assessments, personalization).

---

## **5\) Personas & Use Cases (references)**

([MP Courses AI-copilot](https://drive.google.com/drive/folders/1KoZzZsA5rv4jqaevsezPWoiFAhYU0l0X?usp=drive_link))

* **Alex — Developer** ([PDF](https://drive.google.com/file/d/1BZFapHpnB8YmBh8nv5G43IUZSHk-13nY/view?usp=drive_link))

* **Marcus — Consultant** ([PDF](https://drive.google.com/file/d/1cqICnaqCfKrU9nQRxIKPRVYMvw6LvpRl/view?usp=drive_link))

* **Sarah — Photographer** ([PDF](https://drive.google.com/file/d/1MFnKs06Hv0165uMgEMxHpR6brwUoapSb/view?usp=drive_link))

* **Sophia — Fitness Trainer** ([PDF](https://drive.google.com/file/d/1BF_160TpJsssuuG0a7dGRE7JIrudHxTt/view?usp=drive_link))

---

## **6\) Implementation & Roadmap (estimated ranges)**

**Target:** Beta release before **December**.

**Phase 1 — Growth Plan Approval, Foundation & Core Development (3–5 weeks)**

Initial review and approval of functional scope by Growth, adjustments if needed, followed by core architecture, AI proxy, UX/UI mockups, and base MemberPress integration.

**Phase 2 — Feature Development \- Q/A Version (3–4 weeks)**

Full functionality build put, refinement loop, and advanced features, leading to an internal Q/A-ready version with security and performance checks.

**Phase 3 — Beta Release & Documentation (2–4 weeks)**

Once the Q/A version passes internal checks, Nikola prepares full documentation; limited beta release follows, with Growth re-engaged to verify alignment with the approved plan.

**Phase 4 — Public Launch & Optimization (3–4 weeks)**

General availability release supported by marketing, support enablement, and post-launch optimization based on early adoption data.

---

## **7\) Opportunities & Risks (concise)**

**Opportunities**

* Establish a native, fully integrated AI capability within MemberPress Courses.

* Create a technical foundation for future AI-driven features.

* Accelerate onboarding by reducing the time from concept to a ready-to-edit course.

* Differentiate MemberPress from other WordPress LMS solutions through advanced automation.

* Strengthen the product roadmap by enabling cross-application of AI to other features.

**Risks / Constraints**

* Dependency on external AI providers, with potential cost and availability fluctuations.

* Ensuring consistent quality of AI-generated structures and content.

* Security and privacy compliance for customer data and generated materials.

* Compatibility across diverse WordPress environments.

* Increased support demand during the beta phase.

