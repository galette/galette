{if !isset($js_chosen_id)}
    {assign var="js_chosen_id" value="#id_adh"}
{/if}
    var _adhselect;
    var _next;
    var _prev;
    var _current_page = {$members.filters->current_page|default:1};
    var _membersLoaded = function(members) {
        var _element = $('{$js_chosen_id}').next('.selectize-control');
        for (var id in members) {
            var member = members[id];
            _adhselect[0].selectize.addOption({
                value: member.value,
                text: member.text
            });
        }
        _adhselect[0].selectize.refreshOptions();
        _chosenPages(_element);
    }

    var _chosenPages = function(element) {
    {if isset($members.filters) && $members.filters->pages > $members.filters->current_page}
        if (typeof _next !== 'undefined') {
            if (_current_page >= {$members.filters->pages}) {
                _next.hide();
            } else {
                _next.show();
            }
        }

        if (typeof _next === 'undefined') {
            var _options = $(element).find('.option:not(.pagination)');
            if (_options.length < {$members.filters->show}) {
                //not enough entries
                return;
            }
            _next = $('<div class="option pagination" id="nextpage">{_T string="Load following members..." escape="js"}&nbsp;<i class="ui forward icon"></i></div>');
            _next.on('click', function (event) {
                event.preventDefault();

                var _data = {
                    page: _current_page + 1,
                };

                $.ajax({
                    type: 'POST',
                    dataType: 'json',
                    url : '{path_for name="contributionMembers"}',
                    data: _data,
                    success: function(res){
                        _current_page += 1;
                        _membersLoaded(res.members);
                    },
                    error: function() {
                        alert("{_T string="An error occurred retrieving members :(" escape="js"}");
                    }
                });
            });
            element.append(_next);
        }
    {/if}
    {if isset($members.filters->current_page) && $members.filters->current_page > 1}
        if (typeof _prev !== 'undefined') {
            if (_current_page >= {$members.filters->pages}) {
                _prev.hide();
            } else {
                _prev.show();
            }
        }

        if (typeof _prev === 'undefined') {
            var _options = $(element).find('.option:not(.pagination)');
            if (_options.length < {$members.filters->show}) {
                //not enough entries
                return;
            }
            _prev = $('<div class="option pagination" id="prevpage">{_T string="Load previous members..." escape="js"}&nbsp;<i class="ui backward icon"></i></div>');
            _prev.on('click', function (event) {
                event.preventDefault();

                var _data = {
                    page: _current_page - 1,
                };

                $.ajax({
                    type: 'POST',
                    dataType: 'json',
                    url : '{path_for name="contributionMembers"}',
                    data: _data,
                    success: function(res){
                        _current_page -= 1;
                        _membersLoaded(res.members);
                    },
                    error: function() {
                        alert("{_T string="An error occurred retrieving members :(" escape="js"}");
                    }
                });
            });
            element.append(_prev);
        } else if ($('#prevpage').length > 0) {
            $('#prevpage').remove();
        }
    {/if}
    }

    $(function() {
        _adhselect = $('{$js_chosen_id}').selectize({
            maxItems:       1,
            onDropdownOpen: _chosenPages,
            render: {
                option: function(item, escape) {
                    return '<div class="option">' + escape(item.text) + '</div>';
                }
            },
            load: function(query, callback) {
                if (!query.length) return callback();

                var _this = $(this);
                $.ajax({
                    type: 'POST',
                    url: '{path_for name="contributionMembers" data=["page" => 1, "search" => "PLACEBO"]}'.replace(/PLACEBO/, query),
                    dataType: "json",
                    error: function() {
                        callback();
                    },
                    success: function (res) {
                        var _element = $('{$js_chosen_id}').next('.selectize-control');
                        callback(res.members);
                    }
                });
            }
        });
    });
