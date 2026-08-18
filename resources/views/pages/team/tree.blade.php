@extends('layout_main.app')

@section('content')
    <div class="main-content-container overflow-hidden">
        <div class="row">
            <div class="col-12">
                <div class="card bg-white border border-white rounded-10 mb-4">
                    <div class="card-body p-20">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                            <h4 class="fs-18 fw-medium mb-0">Team Tree</h4>
                            <div class="d-flex gap-2">
                                <button type="button" id="teamTreeRefreshBtn" class="btn btn-outline-secondary btn-sm">
                                    Refresh
                                </button>
                                <button type="button" id="teamTreeCollapseBtn" class="btn btn-outline-secondary btn-sm">
                                    Collapse All
                                </button>
                                <button type="button" id="teamTreeResetBtn" class="btn btn-primary text-white btn-sm">
                                    Reset View
                                </button>
                            </div>
                        </div>

                        <div class="team-tree-search-wrapper mb-3">
                            <label for="teamTreeSearchInput" class="form-label mb-2 fw-medium">Search Team</label>
                            <div class="position-relative">
                                <input id="teamTreeSearchInput" type="text" class="form-control"
                                    placeholder="Search by user ID, name or company..." autocomplete="off" />
                                <div id="teamTreeSearchLoader" class="team-tree-search-loader d-none">
                                    <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                                </div>
                                <div id="teamTreeSearchResults" class="team-tree-search-results d-none"></div>
                            </div>
                        </div>

                        <div id="teamTreeMessage" class="alert alert-danger d-none mb-3" role="alert"></div>

                        <div id="teamTreeViewport" class="team-tree-viewport">
                            <div id="teamTreeLoading" class="text-center py-4">
                                <div class="spinner-border text-primary" role="status"></div>
                                <p class="text-muted mb-0 mt-2">Loading team tree...</p>
                            </div>
                            <div id="teamTreeEmpty" class="alert alert-info d-none mb-0">
                                You currently have no direct referrals.
                            </div>
                            <div id="teamTreeCanvas" class="team-tree-canvas d-none"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .team-tree-viewport {
            border: 1px solid #e5e7eb;
            border-radius: 0.75rem;
            background: #fff;
            min-height: 520px;
            overflow: auto;
            padding: 1.25rem;
        }

        .team-tree-canvas {
            min-width: max-content;
            padding-bottom: 0.5rem;
        }

        .team-tree-list,
        .team-tree-list ul {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .team-tree-children>ul {
            margin-left: 1.2rem;
            padding-left: 0.95rem;
            border-left: 2px solid #EF4923;
        }

        .team-tree-branch {
            position: relative;
            margin-bottom: 0.45rem;
        }

        .team-tree-children .team-tree-branch::before {
            content: '';
            position: absolute;
            left: -0.95rem;
            top: 1.05rem;
            width: 0.95rem;
            border-top: 2px solid #EF4923;
        }

        .team-tree-node {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 0.65rem;
            min-width: 560px;
            padding: 0.5rem 0.65rem;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
            margin: 0.25rem 0;
        }

        .team-tree-node-selected {
            border-color: #ef4923;
            box-shadow: 0 0 0 0.2rem rgba(239, 73, 35, 0.14);
        }

        .team-tree-node-line {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            white-space: nowrap;
            overflow: hidden;
        }

        .team-tree-node-id {
            font-size: 13px;
            color: #334155;
            font-weight: 600;
        }

        .team-tree-node-company {
            font-size: 13px;
            color: #0f172a;
            font-weight: 500;
            flex: 1;
            min-width: 180px;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .team-tree-stat {
            display: inline-flex;
            align-items: center;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 600;
            padding: 0.14rem 0.45rem;
            line-height: 1.25;
        }

        .team-tree-stat-total {
            color: #0f172a;
            background: rgba(148, 163, 184, 0.18);
        }

        .team-tree-stat-direct {
            color: #7c2d12;
            background: rgba(239, 73, 35, 0.12);
        }

        .team-tree-toggle-btn {
            width: 24px;
            height: 24px;
            border: 1px solid #e2e8f0;
            border-radius: 9999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #fff;
            color: #334155;
            padding: 0;
        }

        .team-tree-toggle-btn .material-symbols-outlined {
            font-size: 17px;
        }

        .team-tree-toggle-spacer {
            width: 24px;
            height: 24px;
            display: inline-block;
            flex: 0 0 24px;
        }

        .team-tree-children {
            display: none;
            opacity: 0;
            transform: translateY(-2px);
            transition: opacity 0.18s ease, transform 0.18s ease;
        }

        .team-tree-branch.expanded>.team-tree-children {
            display: block;
            opacity: 1;
            transform: translateY(0);
        }

        .team-tree-children-loading,
        .team-tree-children-empty {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            border: 1px dashed #cbd5e1;
            border-radius: 0.55rem;
            padding: 0.45rem 0.65rem;
            color: #64748b;
            font-size: 12px;
            margin: 0.45rem 0 0.45rem 2.3rem;
            background: #fff;
        }

        @media (max-width: 991.98px) {
            .team-tree-node {
                min-width: 500px;
            }
        }

        .team-tree-search-loader {
            position: absolute;
            right: 0.85rem;
            top: 50%;
            transform: translateY(-50%);
        }

        .team-tree-search-results {
            position: absolute;
            top: calc(100% + 6px);
            left: 0;
            right: 0;
            z-index: 1050;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 0.55rem;
            box-shadow: 0 6px 18px rgba(15, 23, 42, 0.09);
            max-height: 280px;
            overflow-y: auto;
        }

        .team-tree-search-item {
            border: 0;
            background: transparent;
            width: 100%;
            text-align: left;
            padding: 0.55rem 0.75rem;
            font-size: 13px;
        }

        .team-tree-search-item:hover,
        .team-tree-search-item:focus {
            background: rgba(239, 73, 35, 0.06);
        }

        .team-tree-search-empty {
            padding: 0.7rem 0.75rem;
            font-size: 13px;
            color: #64748b;
        }
    </style>
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const endpoints = {
                root: @json(route('team.structure.root')),
                search: @json(route('team.structure.search')),
                childrenTemplate: @json(route('team.structure.children', ['user' => '__USER_UUID__'])),
                pathTemplate: @json(route('team.structure.path', ['user' => '__USER_UUID__'])),
            };

            const rootEl = document.getElementById('teamTreeCanvas');
            const loadingEl = document.getElementById('teamTreeLoading');
            const emptyEl = document.getElementById('teamTreeEmpty');
            const messageEl = document.getElementById('teamTreeMessage');
            const viewportEl = document.getElementById('teamTreeViewport');
            const searchInputEl = document.getElementById('teamTreeSearchInput');
            const searchResultEl = document.getElementById('teamTreeSearchResults');
            const searchLoaderEl = document.getElementById('teamTreeSearchLoader');
            const collapseBtn = document.getElementById('teamTreeCollapseBtn');
            const refreshBtn = document.getElementById('teamTreeRefreshBtn');
            const resetBtn = document.getElementById('teamTreeResetBtn');

            const state = {
                nodes: new Map(),
                rootNodeId: null,
                searchTimer: null,
            };

            function buildUrl(template, userUuid) {
                return template.replace('__USER_UUID__', encodeURIComponent(userUuid));
            }

            function showMessage(text) {
                messageEl.textContent = text;
                messageEl.classList.remove('d-none');
            }

            function hideMessage() {
                messageEl.classList.add('d-none');
                messageEl.textContent = '';
            }

            function escapeHtml(value) {
                return String(value ?? '')
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;')
                    .replaceAll("'", '&#039;');
            }

            function createNodeBranch(node) {
                const branchEl = document.createElement('li');
                branchEl.className = 'team-tree-branch';
                branchEl.dataset.userId = String(node.user_id);

                const nodeEl = document.createElement('div');
                nodeEl.className = 'team-tree-node';
                nodeEl.dataset.userId = String(node.user_id);
                nodeEl.dataset.userUuid = node.user_uuid;

                const toggleMarkup = node.has_children ?
                    '<button type="button" class="team-tree-toggle-btn" data-toggle-user><span class="material-symbols-outlined">chevron_right</span></button>' :
                    '<span class="team-tree-toggle-spacer"></span>';

                nodeEl.innerHTML = `
                    <div class="team-tree-node-line">
                        ${toggleMarkup}
                        <div class="team-tree-node-id">${node.user_label}</div>
                        <div class="team-tree-node-company" title="${escapeHtml(node.company_name)}">${escapeHtml(node.company_name)}</div>
                        <span class="team-tree-stat team-tree-stat-total">${node.total_referrals} Total</span>
                        <span class="team-tree-stat team-tree-stat-direct">${node.direct_referrals} Direct</span>
                    </div>
                `;

                const childrenWrapEl = document.createElement('div');
                childrenWrapEl.className = 'team-tree-children';

                branchEl.appendChild(nodeEl);
                branchEl.appendChild(childrenWrapEl);

                const nodeState = {
                    data: node,
                    branchEl,
                    nodeEl,
                    childrenWrapEl,
                    childrenLoaded: !node.has_children,
                    expanded: false,
                    loading: false,
                };

                state.nodes.set(node.user_id, nodeState);
                return nodeState;
            }

            async function fetchJson(url) {
                const response = await fetch(url, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const payload = await response.json().catch(() => ({}));

                if (!response.ok) {
                    throw new Error(payload.message || 'Request failed');
                }

                return payload;
            }

            function setNodeExpanded(nodeState, expanded) {
                nodeState.expanded = expanded;
                nodeState.branchEl.classList.toggle('expanded', expanded);

                const toggleBtn = nodeState.nodeEl.querySelector('[data-toggle-user]');
                if (toggleBtn) {
                    const icon = toggleBtn.querySelector('.material-symbols-outlined');
                    icon.textContent = expanded ? 'expand_more' : 'chevron_right';
                }
            }

            function showChildrenLoading(nodeState) {
                nodeState.childrenWrapEl.innerHTML = `
                    <div class="team-tree-children-loading">
                        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                        <span>Loading...</span>
                    </div>
                `;
            }

            function showChildrenEmpty(nodeState) {
                nodeState.childrenWrapEl.innerHTML = '<div class="team-tree-children-empty">No direct referrals</div>';
            }

            function renderChildren(nodeState, children) {
                if (!children.length) {
                    showChildrenEmpty(nodeState);
                    nodeState.childrenLoaded = true;
                    nodeState.data.has_children = false;
                    const toggleBtn = nodeState.nodeEl.querySelector('[data-toggle-user]');
                    if (toggleBtn) {
                        toggleBtn.outerHTML = '<span class="team-tree-toggle-spacer"></span>';
                    }
                    return;
                }

                const ulEl = document.createElement('ul');

                children.forEach((childNode) => {
                    const childState = createNodeBranch(childNode);
                    ulEl.appendChild(childState.branchEl);
                });

                nodeState.childrenWrapEl.innerHTML = '';
                nodeState.childrenWrapEl.appendChild(ulEl);
                nodeState.childrenLoaded = true;
            }

            async function expandNodeById(userId) {
                const nodeState = state.nodes.get(userId);
                if (!nodeState || !nodeState.data.has_children || nodeState.loading) {
                    return;
                }

                if (!nodeState.childrenLoaded) {
                    nodeState.loading = true;
                    showChildrenLoading(nodeState);
                    setNodeExpanded(nodeState, true);

                    const toggleBtn = nodeState.nodeEl.querySelector('[data-toggle-user]');
                    if (toggleBtn) {
                        toggleBtn.disabled = true;
                    }

                    try {
                        const data = await fetchJson(buildUrl(endpoints.childrenTemplate, nodeState.data.user_uuid));
                        renderChildren(nodeState, data.children || []);
                        setNodeExpanded(nodeState, true);
                    } finally {
                        nodeState.loading = false;
                        if (toggleBtn) {
                            toggleBtn.disabled = false;
                        }
                    }

                    return;
                }

                setNodeExpanded(nodeState, !nodeState.expanded);
            }

            function collapseAll() {
                state.nodes.forEach((nodeState, userId) => {
                    if (userId !== state.rootNodeId) {
                        setNodeExpanded(nodeState, false);
                    }
                });
            }

            function clearTree() {
                state.nodes.clear();
                state.rootNodeId = null;
                rootEl.innerHTML = '';
            }

            async function loadRootTree() {
                hideMessage();
                loadingEl.classList.remove('d-none');
                emptyEl.classList.add('d-none');
                rootEl.classList.add('d-none');
                clearTree();

                try {
                    const payload = await fetchJson(endpoints.root);
                    if (!payload.root) {
                        emptyEl.classList.remove('d-none');
                        return;
                    }

                    const treeRootUl = document.createElement('ul');
                    treeRootUl.className = 'team-tree-list';

                    const rootState = createNodeBranch(payload.root);
                    treeRootUl.appendChild(rootState.branchEl);
                    state.rootNodeId = payload.root.user_id;

                    const initialChildren = payload.children || [];
                    if (initialChildren.length) {
                        renderChildren(rootState, initialChildren);
                        setNodeExpanded(rootState, true);
                    } else if (payload.root.has_children) {
                        rootState.childrenLoaded = false;
                    } else {
                        showChildrenEmpty(rootState);
                        setNodeExpanded(rootState, true);
                    }

                    rootEl.appendChild(treeRootUl);
                    rootEl.classList.remove('d-none');
                } catch (error) {
                    showMessage(error.message || 'Failed to load team tree.');
                } finally {
                    loadingEl.classList.add('d-none');
                }
            }

            function highlightNode(nodeEl) {
                nodeEl.classList.add('team-tree-node-selected');
                setTimeout(() => nodeEl.classList.remove('team-tree-node-selected'), 2600);
            }

            async function expandPathToUser(userUuid) {
                hideMessage();

                try {
                    const data = await fetchJson(buildUrl(endpoints.pathTemplate, userUuid));
                    const path = data.path || [];

                    if (!path.length) {
                        showMessage('No team path was found for the selected user.');
                        return;
                    }

                    if (state.rootNodeId === null || state.rootNodeId !== path[0].user_id) {
                        await loadRootTree();
                    }

                    for (let i = 0; i < path.length - 1; i++) {
                        await expandNodeById(path[i].user_id);
                    }

                    const targetNode = state.nodes.get(data.selected_user_id)?.nodeEl;
                    if (!targetNode) {
                        showMessage('Selected user could not be rendered in the current tree.');
                        return;
                    }

                    targetNode.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center',
                        inline: 'center',
                    });
                    highlightNode(targetNode);
                } catch (error) {
                    showMessage(error.message || 'Could not navigate to the selected user.');
                }
            }

            function renderSearchResults(results) {
                searchResultEl.innerHTML = '';

                if (!results.length) {
                    searchResultEl.innerHTML = '<div class="team-tree-search-empty">No users found</div>';
                    searchResultEl.classList.remove('d-none');
                    return;
                }

                results.forEach((result) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'team-tree-search-item';
                    button.textContent = result.label;
                    button.addEventListener('click', async () => {
                        searchInputEl.value = result.label;
                        searchResultEl.classList.add('d-none');
                        await expandPathToUser(result.user_uuid);
                    });
                    searchResultEl.appendChild(button);
                });

                searchResultEl.classList.remove('d-none');
            }

            async function searchUsers(query) {
                if (query.length < 2) {
                    searchResultEl.classList.add('d-none');
                    searchResultEl.innerHTML = '';
                    return;
                }

                searchLoaderEl.classList.remove('d-none');
                try {
                    const url = new URL(endpoints.search, window.location.origin);
                    url.searchParams.set('q', query);
                    const payload = await fetchJson(url.toString());
                    renderSearchResults(payload.results || []);
                } catch (error) {
                    showMessage(error.message || 'Search failed.');
                } finally {
                    searchLoaderEl.classList.add('d-none');
                }
            }

            rootEl.addEventListener('click', async function(event) {
                const toggleBtn = event.target.closest('[data-toggle-user]');
                if (!toggleBtn) {
                    return;
                }

                const nodeEl = toggleBtn.closest('.team-tree-node');
                if (!nodeEl) {
                    return;
                }

                const userId = Number(nodeEl.dataset.userId);
                if (!Number.isFinite(userId)) {
                    return;
                }

                try {
                    await expandNodeById(userId);
                } catch (error) {
                    showMessage(error.message || 'Could not load direct referrals for this user.');
                }
            });

            collapseBtn.addEventListener('click', function() {
                collapseAll();
            });

            refreshBtn.addEventListener('click', async function() {
                await loadRootTree();
            });

            resetBtn.addEventListener('click', async function() {
                searchInputEl.value = '';
                searchResultEl.classList.add('d-none');
                await loadRootTree();
            });

            searchInputEl.addEventListener('input', function(event) {
                const query = event.target.value.trim();
                clearTimeout(state.searchTimer);
                state.searchTimer = setTimeout(() => {
                    searchUsers(query);
                }, 300);
            });

            document.addEventListener('click', function(event) {
                if (!event.target.closest('.team-tree-search-wrapper')) {
                    searchResultEl.classList.add('d-none');
                }
            });

            loadRootTree().then(function() {
                viewportEl.scrollTo({
                    top: 0,
                    left: 0,
                });
            });
        });
    </script>
@endpush
