/**
 * Sticker default skin
 */
(function($) {
	'use strict';

	var $doc = $(document);
	var ALLOW_TYPES = ['image/jpeg', 'image/png', 'image/gif'];

	function reload() {
		window.location.reload();
	}

	/* 상세 화면 : 구매하기 */
	$doc.on('click', '.js-stk-buy', function() {
		var $btn = $(this);
		var price = $btn.data('price');

		if (!confirm(price + ' 포인트를 사용하여 스티커를 구매하시겠습니까?')) {
			return false;
		}

		$btn.prop('disabled', true);
		exec_json('sticker.procStickerBuy', {
			mid: $btn.data('mid'),
			sticker_srl: $btn.data('sticker-srl')
		}, function() {
			alert('구매하였습니다.');
			reload();
		}, function() {
			$btn.prop('disabled', false);
		});

		return false;
	});

	/* 상세 화면 : 보유한 스티커 버리기 */
	$doc.on('click', '.js-stk-discard', function() {
		var $btn = $(this);

		if (!confirm('보유중인 이 스티커를 버리시겠습니까?')) {
			return false;
		}

		$btn.prop('disabled', true);
		exec_json('sticker.procStickerBuyDelete', {
			mid: $btn.data('mid'),
			sticker_srl: $btn.data('sticker-srl')
		}, function() {
			alert('삭제하였습니다.');
			reload();
		}, function() {
			$btn.prop('disabled', false);
		});

		return false;
	});

	/* 내 스티커 : 순서 변경 */
	$doc.on('click', '.js-stk-move', function() {
		var $btn = $(this);

		exec_json('sticker.procStickerBuyOrderChange', {
			mid: $btn.data('mid'),
			sticker_srl: $btn.data('sticker-srl'),
			move: $btn.data('move')
		}, reload);

		return false;
	});

	/* 내 스티커 : 버리기 */
	$doc.on('click', '.js-stk-mydelete', function() {
		var $btn = $(this);
		var title = $btn.data('title');

		if (!confirm('보유중인 "' + title + '"을(를) 삭제하시겠습니까?')) {
			return false;
		}

		exec_json('sticker.procStickerBuyDelete', {
			mid: $btn.data('mid'),
			sticker_srl: $btn.data('sticker-srl')
		}, function() {
			alert('삭제하였습니다.');
			reload();
		});

		return false;
	});

	/**
	 * 스티커 업로더.
	 *
	 * 여러 개의 file 태그를 드래그&드롭 영역 하나로 대체한다. 제출 직전에
	 * 서버가 기대하는 이름(sticker_main_file / sticker_file[] / sticker_file_N)의
	 * file 태그를 만들어 DataTransfer로 파일을 넣어 준다.
	 */
	function StickerUploader(root) {
		this.root = root;
		this.mode = root.getAttribute('data-mode');
		this.mid = root.getAttribute('data-mid');
		this.stickerSrl = root.getAttribute('data-sticker-srl');
		this.minTotal = parseInt(root.getAttribute('data-min-total'), 10);
		this.maxTotal = parseInt(root.getAttribute('data-max-total'), 10);
		this.maxSlot = parseInt(root.getAttribute('data-max-slot'), 10);
		this.minSlot = parseInt(root.getAttribute('data-min-slot'), 10);
		this.maxSize = parseInt(root.getAttribute('data-max-size'), 10) * 1024;

		this.list = root.querySelector('.stk-uploader__list');
		this.picker = root.querySelector('.js-stk-picker');
		this.inputs = root.querySelector('.js-stk-inputs');
		this.counter = root.querySelector('.js-stk-count');

		this.seq = 0;
		this.replaceTarget = null;
		this.items = this._readExistingItems();

		this._bind();
		this.render();
	}

	/* 서버가 그려 준 기존 이미지를 상태로 흡수한다. */
	StickerUploader.prototype._readExistingItems = function() {
		var self = this;

		return Array.prototype.map.call(this.list.querySelectorAll('.stk-uploader__item'), function(node) {
			var no = parseInt(node.getAttribute('data-no'), 10);

			return {
				id: 'e' + (self.seq++),
				kind: 'existing',
				no: no,
				url: node.querySelector('img, video').getAttribute('src'),
				main: no === 0,
				removable: node.getAttribute('data-removable') === '1',
				file: null
			};
		});
	};

	StickerUploader.prototype._bind = function() {
		var self = this;

		this.picker.addEventListener('change', function() {
			self.add(this.files);
			this.value = '';
		});

		this.root.addEventListener('click', function(e) {
			var action = e.target.closest('[data-action]');
			if (action) {
				e.preventDefault();
				e.stopPropagation();
				self[action.getAttribute('data-action')](action.closest('.stk-uploader__item').getAttribute('data-id'));
				return;
			}
			if (!e.target.closest('.stk-uploader__item')) {
				self.replaceTarget = null;
				self.picker.multiple = true;
				self.picker.click();
			}
		});

		['dragenter', 'dragover'].forEach(function(name) {
			self.root.addEventListener(name, function(e) {
				e.preventDefault();
				self.root.classList.add('is-dragover');
			});
		});

		['dragleave', 'drop'].forEach(function(name) {
			self.root.addEventListener(name, function(e) {
				e.preventDefault();
				if (name === 'dragleave' && self.root.contains(e.relatedTarget)) {
					return;
				}
				self.root.classList.remove('is-dragover');
			});
		});

		this.root.addEventListener('drop', function(e) {
			self.replaceTarget = null;
			self.add(e.dataTransfer.files);
		});
	};

	StickerUploader.prototype.add = function(fileList) {
		var files = Array.prototype.slice.call(fileList || []);
		var self = this;
		var rejected = [];

		files = files.filter(function(file) {
			if ($.inArray(file.type, ALLOW_TYPES) === -1) {
				rejected.push(file.name + ' : 지원하지 않는 형식');
				return false;
			}
			if (file.size > self.maxSize) {
				rejected.push(file.name + ' : 용량 초과');
				return false;
			}
			return true;
		});

		if (rejected.length) {
			alert('아래 파일은 제외되었습니다.\n\n' + rejected.join('\n'));
		}

		/* 기존 이미지 교체 */
		if (this.replaceTarget) {
			if (files.length) {
				var target = this._find(this.replaceTarget);
				target.file = files[0];
				target.url = URL.createObjectURL(files[0]);
			}
			this.replaceTarget = null;
			this.render();
			return;
		}

		var room = this.maxTotal - this.items.length;
		if (files.length > room) {
			alert('최대 ' + this.maxTotal + '장까지 등록할 수 있습니다.');
			files = files.slice(0, Math.max(room, 0));
		}

		files.forEach(function(file) {
			self.items.push({
				id: 'n' + (self.seq++),
				kind: 'new',
				no: null,
				url: URL.createObjectURL(file),
				main: false,
				removable: true,
				file: file
			});
		});

		/* 대표가 없으면 첫 장을 대표로 */
		if (this.items.length && !this.items.some(function(item) { return item.main; })) {
			this.items[0].main = true;
		}

		this.render();
	};

	StickerUploader.prototype._find = function(id) {
		return this.items.filter(function(item) { return item.id === id; })[0];
	};

	StickerUploader.prototype.setMain = function(id) {
		var target = this._find(id);

		this.items.forEach(function(item) { item.main = (item === target); });
		this.render();
	};

	StickerUploader.prototype.replace = function(id) {
		this.replaceTarget = id;
		this.picker.multiple = false;
		this.picker.click();
	};

	StickerUploader.prototype.remove = function(id) {
		var self = this;
		var target = this._find(id);

		if (target.kind === 'new') {
			this._drop(target);
			return;
		}

		if (!target.removable) {
			alert('필수 이미지는 삭제할 수 없습니다. 이미지를 눌러 다른 파일로 교체해주세요.');
			return;
		}

		if (!confirm('등록된 이미지를 삭제합니다. 되돌릴 수 없습니다.')) {
			return;
		}

		exec_json('sticker.procStickerFileDelete', {
			mid: this.mid,
			sticker_srl: this.stickerSrl,
			no: target.no
		}, function() {
			self._drop(target);
		});
	};

	StickerUploader.prototype._drop = function(target) {
		if (target.file) {
			URL.revokeObjectURL(target.url);
		}
		this.items.splice(this.items.indexOf(target), 1);
		if (this.items.length && !this.items.some(function(item) { return item.main; })) {
			this.items[0].main = true;
		}
		this.render();
	};

	StickerUploader.prototype.render = function() {
		var self = this;
		var html = '';

		this.items.forEach(function(item) {
			var classes = ['stk-uploader__item'];
			if (item.main) {
				classes.push('is-main');
			}
			if (item.kind === 'existing') {
				classes.push('is-existing');
			}

			html += '<li class="' + classes.join(' ') + '" data-id="' + item.id + '">';

			if (/\.mp4$/i.test(item.url)) {
				html += '<video src="' + item.url + '" poster="' + item.url.slice(0, -4) + '.webp" autoplay muted loop playsinline></video>';
			}
			else {
				html += '<img src="' + item.url + '" alt="" />';
			}

			if (item.kind === 'new' || item.removable) {
				html += '<button type="button" class="stk-uploader__remove" data-action="remove" title="삭제">&times;</button>';
			}

			if (item.kind === 'existing') {
				html += '<button type="button" class="stk-uploader__replace" data-action="replace">교체</button>';
			}

			if (item.main) {
				html += '<span class="stk-uploader__badge">대표</span>';
			}
			else if (self.mode === 'new') {
				/* 등록 화면에서만 대표를 자유롭게 고를 수 있다. 수정 화면의 대표(0번 슬롯)는 교체만 가능하다. */
				html += '<button type="button" class="stk-uploader__badge stk-uploader__badge--action" data-action="setMain">대표 지정</button>';
			}

			html += '</li>';
		});

		this.list.innerHTML = html;
		this.counter.textContent = this.items.length;
		this.root.classList.toggle('is-filled', this.items.length > 0);

		this._syncInputs();
	};

	/* 상태를 서버가 기대하는 file 태그로 옮긴다. */
	StickerUploader.prototype._syncInputs = function() {
		var self = this;
		var used = {};

		this.inputs.innerHTML = '';

		this.items.forEach(function(item) {
			if (item.kind === 'existing') {
				used[item.no] = true;
			}
		});

		this.items.forEach(function(item) {
			if (!item.file) {
				return;
			}

			var name;
			if (self.mode === 'new') {
				name = item.main ? 'sticker_main_file' : 'sticker_file[]';
			} else if (item.kind === 'existing') {
				name = item.no === 0 ? 'sticker_main_file' : 'sticker_file_' + item.no;
			} else if (item.main) {
				name = 'sticker_main_file';
			} else {
				name = 'sticker_file_' + self._takeSlot(used);
			}

			self.inputs.appendChild(self._makeInput(name, item.file));
		});
	};

	StickerUploader.prototype._takeSlot = function(used) {
		for (var i = 1; i <= this.maxSlot; i++) {
			if (!used[i]) {
				used[i] = true;
				return i;
			}
		}
		return this.maxSlot;
	};

	StickerUploader.prototype._makeInput = function(name, file) {
		var input = document.createElement('input');
		var transfer = new DataTransfer();

		transfer.items.add(file);
		input.type = 'file';
		input.name = name;
		input.files = transfer.files;

		return input;
	};

	StickerUploader.prototype.validate = function() {
		if (this.mode === 'new') {
			if (this.items.length < this.minTotal) {
				alert('스티커 이미지를 최소 ' + this.minTotal + '장 등록해주세요.');
				return false;
			}
			if (!this.items.some(function(item) { return item.main; })) {
				alert('대표 이미지를 지정해주세요.');
				return false;
			}
		}

		return true;
	};

	/**
	 * 태그 입력기.
	 *
	 * 빠른 등록 버튼과 직접 입력으로 태그를 모아 hidden input에 쉼표로 합쳐 넣는다.
	 */
	function StickerTagger(root) {
		this.root = root;
		this.max = parseInt(root.getAttribute('data-max'), 10);
		this.maxLength = parseInt(root.getAttribute('data-maxlength'), 10);

		this.list = root.querySelector('.js-stk-taglist');
		this.input = root.querySelector('.js-stk-taginput');
		this.value = root.querySelector('.js-stk-tagvalue');

		this.tags = [];
		this._bind();
		this.add(this.value.value);
	}

	StickerTagger.prototype._bind = function() {
		var self = this;

		this.root.addEventListener('click', function(e) {
			var preset = e.target.closest('.js-stk-tagpreset');
			if (preset) {
				self.toggle(preset.getAttribute('data-tag'));
				return;
			}
			if (e.target.closest('.js-stk-tagadd')) {
				self.flush();
				return;
			}
			var remove = e.target.closest('.js-stk-tagremove');
			if (remove) {
				self.remove(remove.getAttribute('data-tag'));
			}
		});

		/**
		 * 구분자는 keydown이 아니라 input에서 처리한다.
		 * 한글 IME는 조합 중에 keydown을 먼저 보내고 그 뒤에 조합 문자를 확정하기 때문에,
		 * keydown 시점의 입력값을 지우면 확정된 마지막 글자가 입력창에 다시 남는다.
		 */
		this.input.addEventListener('input', function() {
			if (!/[,\s]/.test(this.value)) {
				return;
			}

			var parts = this.value.split(/[,\s]+/);
			var rest = parts.pop();

			this.value = rest;
			self.add(parts.join(','));
		});

		this.input.addEventListener('keydown', function(e) {
			if (e.key !== 'Enter') {
				return;
			}

			// 폼이 제출되지 않도록 조합 중에도 기본 동작은 막는다.
			e.preventDefault();
			if (e.isComposing || e.keyCode === 229) {
				return;
			}

			self.flush();
		});
	};

	/**
	 * 입력창에 남아 있는 값을 태그로 옮긴다.
	 */
	StickerTagger.prototype.flush = function() {
		this.add(this.input.value);
		this.input.value = '';
		this.input.focus();
	};

	StickerTagger.prototype._push = function(tag) {
		tag = String(tag).replace(/^#+/, '').trim();
		if (!tag || $.inArray(tag, this.tags) !== -1) {
			return true;
		}
		if (this.tags.length >= this.max) {
			return false;
		}

		this.tags.push(tag.slice(0, this.maxLength));
		return true;
	};

	StickerTagger.prototype._done = function(has_room) {
		if (!has_room) {
			alert('태그는 최대 ' + this.max + '개까지 추가할 수 있습니다.');
		}
		this.render();
	};

	/**
	 * 구분자가 섞인 문자열을 여러 태그로 나눠 추가한다.
	 */
	StickerTagger.prototype.add = function(text) {
		var self = this;
		var has_room = true;

		String(text || '').split(/[,\s]+/).forEach(function(tag) {
			has_room = self._push(tag) && has_room;
		});

		this._done(has_room);
	};

	/**
	 * 빠른 등록 태그처럼 공백이 포함될 수 있는 값을 통째로 추가한다.
	 */
	StickerTagger.prototype.toggle = function(tag) {
		if ($.inArray(tag, this.tags) !== -1) {
			this.remove(tag);
			return;
		}

		this._done(this._push(tag));
	};

	StickerTagger.prototype.remove = function(tag) {
		var index = $.inArray(tag, this.tags);
		if (index !== -1) {
			this.tags.splice(index, 1);
			this.render();
		}
	};

	StickerTagger.prototype.render = function() {
		var html = '';
		var chosen = {};

		this.tags.forEach(function(tag) {
			chosen[tag] = true;
			html += '<li class="stk-tagger__tag">#' + $('<span>').text(tag).html();
			html += '<button type="button" class="js-stk-tagremove" data-tag="' + $('<span>').text(tag).html() + '" aria-label="태그 삭제">&times;</button></li>';
		});

		this.list.innerHTML = html;
		this.value.value = this.tags.join(', ');

		Array.prototype.forEach.call(this.root.querySelectorAll('.js-stk-tagpreset'), function(preset) {
			preset.classList.toggle('is-chosen', !!chosen[preset.getAttribute('data-tag')]);
		});
	};

	/* 등록/수정 폼 */
	$(function() {
		var root = document.querySelector('.js-stk-uploader');
		if (root) {
			root.uploader = new StickerUploader(root);
		}

		var tagger = document.querySelector('.js-stk-tagger');
		if (tagger) {
			new StickerTagger(tagger);
		}
	});

	$doc.on('submit', '.js-stk-form', function() {
		var $form = $(this);
		var title = $.trim($form.find('input[name=title]').val() || '');
		var price = parseInt($form.find('input[name=price]').val(), 10);
		var min = parseInt($form.find('input[name=price]').attr('min'), 10);
		var max = parseInt($form.find('input[name=price]').attr('max'), 10);
		var root = this.querySelector('.js-stk-uploader');

		if (!title) {
			alert('제목을 입력해주세요.');
			return false;
		}

		if (isNaN(price) || price < min || price > max) {
			alert('판매 포인트는 ' + min + 'P 이상 ' + max + 'P 이하로 입력해주세요.');
			return false;
		}

		if (root && root.uploader && !root.uploader.validate()) {
			return false;
		}

		return true;
	});

})(jQuery);
