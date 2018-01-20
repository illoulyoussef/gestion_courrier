﻿import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import {Observable} from 'rxjs/Observable';
import { Entite } from '../_models/index';

@Injectable()
export class EntiteService {
    private serviceUrl = 'http://localhost:4200/assets/demo_entite.txt';

    constructor(private http: HttpClient) { }

    getAll() : Observable<Entite[]> {
        return this.http.get<Entite[]>(this.serviceUrl);
    }

    /**getById(id: number) {
        return this.http.get('/api/users/' + id);
    }

    create(user: User) {
        return this.http.post('/api/users', user);
    }

    update(user: User) {
        return this.http.put('/api/users/' + user.id, user);
    }

    delete(id: number) {
        return this.http.delete('/api/users/' + id);
    }**/
}